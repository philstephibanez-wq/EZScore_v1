#!/usr/bin/env python3
from __future__ import annotations
import argparse, json
from pathlib import Path
import numpy as np
import librosa

NOTE_NAMES=["C","C#","D","D#","E","F","F#","G","G#","A","A#","B"]
MAJOR=np.array([6.35,2.23,3.48,2.33,4.38,4.09,2.52,5.19,2.39,3.66,2.29,2.88],dtype=float)
MINOR=np.array([6.33,2.68,3.52,5.38,2.60,3.53,2.54,4.75,3.98,2.69,3.34,3.17],dtype=float)

QUALITIES={
 "major":([0,4,7],""),
 "minor":([0,3,7],"m"),
 "7":([0,4,7,10],"7"),
 "maj7":([0,4,7,11],"maj7"),
 "min7":([0,3,7,10],"m7"),
 "sus2":([0,2,7],"sus2"),
 "sus4":([0,5,7],"sus4"),
 "dim":([0,3,6],"dim"),
 "aug":([0,4,8],"aug"),
 "6":([0,4,7,9],"6"),
 "min6":([0,3,7,9],"m6"),
 "add9":([0,2,4,7],"add9"),
}
LEVELS={
 "beginner":["major","minor"],
 "intermediate":["major","minor","7","maj7","min7","sus2","sus4","dim","aug"],
 "expert":list(QUALITIES.keys()),
}

def cosine(a,b):
    den=float(np.linalg.norm(a)*np.linalg.norm(b))
    return 0.0 if den<=1e-12 else float(np.dot(a,b)/den)

def chord_templates(level):
    out=[]
    for root in range(12):
        for quality in LEVELS[level]:
            intervals,suffix=QUALITIES[quality]
            v=np.full(12,0.06,dtype=float)
            v[root]=1.30
            for interval in intervals:
                v[(root+interval)%12]=1.0
            v/=max(float(np.linalg.norm(v)),1e-9)
            out.append((NOTE_NAMES[root]+suffix,v))
    return out

def detect_key(mean):
    if float(np.sum(mean))<=1e-9:
        return None
    x=mean/max(float(np.linalg.norm(mean)),1e-9)
    best_name,best_score=None,-1e9
    for root in range(12):
        for profile,suffix in ((np.roll(MAJOR,root),""),(np.roll(MINOR,root),"m")):
            score=cosine(x,profile)
            if score>best_score:
                best_name,best_score=NOTE_NAMES[root]+suffix,score
    return best_name

def detect_signature(onset_env,beat_frames):
    if len(beat_frames)<12:
        return "4/4"
    strengths=onset_env[np.clip(beat_frames,0,len(onset_env)-1)]
    strengths=(strengths-np.mean(strengths))/(np.std(strengths)+1e-9)
    def score(period):
        means=[]
        for phase in range(period):
            vals=strengths[np.arange(len(strengths))%period==phase]
            if vals.size:
                means.append(float(np.mean(vals)))
        return max(means)-float(np.mean(means)) if means else -1e9
    cand={"3/4":score(3),"4/4":score(4),"6/8":score(6)}
    ordered=sorted(cand.items(),key=lambda kv:kv[1],reverse=True)
    if len(ordered)>1 and ordered[0][1]-ordered[1][1]<0.08:
        return "4/4"
    return ordered[0][0]

def numerator(signature):
    try:return max(1,int(signature.split("/",1)[0]))
    except Exception:return 4

def smooth(labels,conf,level):
    result=labels[:]
    if len(result)<3:return result
    threshold={"beginner":0.80,"intermediate":0.70,"expert":0.58}[level]
    for i in range(1,len(result)-1):
        if result[i-1]==result[i+1]!=result[i] and conf[i]<threshold:
            result[i]=result[i-1]
    if level=="beginner":
        for i in range(1,len(result)-1):
            if result[i]!=result[i-1] and result[i]!=result[i+1]:
                result[i]=result[i-1]
    return result

def analyse(audio,level,requested_signature):
    y,sr=librosa.load(str(audio),sr=22050,mono=True)
    if y.size<sr:raise RuntimeError("audio too short")
    y=librosa.util.normalize(y)
    harmonic=librosa.effects.harmonic(y,margin=4.0)
    hop=512
    onset=librosa.onset.onset_strength(y=harmonic,sr=sr,hop_length=hop)
    tempo_value,beat_frames=librosa.beat.beat_track(onset_envelope=onset,sr=sr,hop_length=hop,trim=False)
    beat_frames=np.asarray(beat_frames,dtype=int)
    beat_times=librosa.frames_to_time(beat_frames,sr=sr,hop_length=hop)
    tempo_arr=np.asarray(tempo_value).reshape(-1)
    tempo=float(tempo_arr[0]) if tempo_arr.size else 0.0
    duration=float(librosa.get_duration(y=y,sr=sr))
    if len(beat_times)<4:
        step=60.0/tempo if tempo>20 else 0.5
        beat_times=np.arange(0.0,duration,step,dtype=float)
        beat_frames=librosa.time_to_frames(beat_times,sr=sr,hop_length=hop)

    signature=detect_signature(onset,beat_frames) if requested_signature=="auto" else requested_signature
    bpm=numerator(signature)
    chroma=librosa.feature.chroma_cqt(y=harmonic,sr=sr,hop_length=hop)
    chroma=np.maximum(chroma,0.0)
    key=detect_key(np.mean(chroma,axis=1))
    rms=librosa.feature.rms(y=y,hop_length=hop)[0]
    floor=float(np.percentile(rms,12)) if rms.size else 0.0
    templates=chord_templates(level)

    labels=[];confidences=[]
    for i,start in enumerate(beat_times):
        end=beat_times[i+1] if i+1<len(beat_times) else min(duration,start+max(0.25,60.0/max(tempo,60.0)))
        f0=max(0,int(librosa.time_to_frames(start,sr=sr,hop_length=hop)))
        f1=min(chroma.shape[1],max(f0+1,int(librosa.time_to_frames(end,sr=sr,hop_length=hop))))
        if f0>=chroma.shape[1]:
            labels.append(labels[-1] if labels else ".");confidences.append(0.0);continue
        seg_mean=np.mean(chroma[:,f0:f1],axis=1)
        local=float(np.mean(rms[min(f0,len(rms)-1):min(max(f1,f0+1),len(rms))])) if rms.size else 1.0
        if local<=max(0.003,floor*0.55) or float(np.sum(seg_mean))<=1e-6:
            labels.append(".");confidences.append(1.0);continue
        seg=seg_mean/max(float(np.linalg.norm(seg_mean)),1e-9)
        scored=sorted(((float(np.dot(seg,t)),label) for label,t in templates),reverse=True)
        best_score,best_label=scored[0]
        second=scored[1][0] if len(scored)>1 else 0.0
        confidence=max(0.0,min(1.0,(best_score-second)*3.0+best_score*0.35))
        labels.append(best_label);confidences.append(confidence)

    labels=smooth(labels,confidences,level)
    beats=[{
        "start_ms":int(round(float(t)*1000)),
        "measure_index":i//bpm,
        "beat_index":i%bpm,
        "subdivision_index":None,
    } for i,t in enumerate(beat_times)]

    chords=[];previous=None
    for i,(label,confidence) in enumerate(zip(labels,confidences)):
        if label==previous:continue
        chords.append({
            "start_ms":int(round(float(beat_times[i])*1000)),
            "measure_index":i//bpm,
            "beat_index":i%bpm,
            "subdivision_index":None,
            "chord":label,
            "confidence":round(float(confidence),4),
        })
        previous=label

    return {
        "ok":True,"version":"r33","level":level,"tempo_bpm":round(tempo,3),
        "time_signature":signature,"key":key,"beats":beats,"chords":chords
    }

def main():
    p=argparse.ArgumentParser()
    p.add_argument("--audio",required=True)
    p.add_argument("--level",choices=("beginner","intermediate","expert"),default="intermediate")
    p.add_argument("--time-signature",default="auto")
    a=p.parse_args()
    try:
        print(json.dumps(analyse(Path(a.audio),a.level,a.time_signature),ensure_ascii=False))
        return 0
    except Exception as exc:
        print(json.dumps({"ok":False,"error":f"{type(exc).__name__}: {exc}"},ensure_ascii=False))
        return 1

if __name__=="__main__":
    raise SystemExit(main())
