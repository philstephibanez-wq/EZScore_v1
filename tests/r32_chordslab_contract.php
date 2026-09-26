<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$entity=file_get_contents($root.'/src/Domain/Song/SongTimelineEvent.php');
$controller=file_get_contents($root.'/src/Controller_SongLabController.php');
$twig=file_get_contents($root.'/templates/song/chordslab.html.twig');
$js=file_get_contents($root.'/public/assets/js/chordslab.js');
$patch=file_get_contents($root.'/scripts/apply_r32_chordslab_timeline.py');
$migration=file_get_contents($root.'/migrations/Version20260926130000.php');

$checks=[
 'canonical timeline entity'=>str_contains($entity,"TYPE_CHORD = 'chord'") && str_contains($entity,'startMs'),
 'original and override separated'=>str_contains($entity,'originalValue') && str_contains($entity,'overrideValue'),
 'timeline migration'=>str_contains($migration,'song_timeline_events'),
 'key and analysis level persisted'=>str_contains($migration,'key_signature') && str_contains($migration,'chord_analysis_level'),
 'real ChordsLab route'=>str_contains($controller,"render('song/chordslab.html.twig'"),
 'inline persistence endpoint'=>str_contains($controller,'app_song_chordslab_event'),
 'reset endpoint'=>str_contains($controller,'app_song_chordslab_reset'),
 'capo realtime render'=>str_contains($js,'pc - capo'),
 'time signature realtime render'=>str_contains($js,'parseSignature'),
 'carry chord across measures'=>str_contains($js,'carry:true'),
 'diagram renderer'=>str_contains($js,'SHAPES') && str_contains($js,'<svg'),
 'existing player reused'=>str_contains($twig,'ezscore-audio-engine.js') && str_contains($twig,'stems-mixer.js'),
 'tracks collapsed by default'=>str_contains($twig,'<details class="chordslab-collapse">'),
 'FX collapsed by default'=>substr_count($twig,'<details class="chordslab-collapse">') >= 2,
 'timeline player bridge'=>str_contains($patch,'ezscore:audio-timeupdate'),
 'desktop responsive'=>str_contains(file_get_contents($root.'/public/assets/css/chordslab.css'),'grid-template-columns:2fr'),
 'tablet responsive'=>str_contains(file_get_contents($root.'/public/assets/css/chordslab.css'),'@media(max-width:1024px)'),
 'smartphone responsive'=>str_contains(file_get_contents($root.'/public/assets/css/chordslab.css'),'@media(max-width:640px)'),
 'no mock chords in template'=>!str_contains($twig,'[ Em--- ]'),
];

foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "OK: $label\n";}
echo "\n".count($checks)." R32 ChordsLab checks passed.\n";
