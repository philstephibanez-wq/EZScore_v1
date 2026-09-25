# EZScore_v1 — R24.5 CUDA Torch fix

R24.4 échouait parce qu'il demandait simultanément :

```text
torch
torchvision
torchaudio
```

sur l'index CUDA 13.2, alors que `torchaudio` n'y possède pas de wheel compatible avec le Python 3.13 utilisé ici.

Le pipeline STEMS EZScore_v1 n'a pas besoin de `torchaudio` pour cette étape.

R24.5 remplace donc uniquement le paquet `torch` CPU par le paquet `torch` CUDA :

```text
H:\EZScore_v1\.venv-py313\Scripts\python.exe
```

avec :

```text
https://download.pytorch.org/whl/cu132
```

Le script reteste ensuite CUDA, le GPU, `bs_roformer` et `mel_band_roformer`.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R24_5_CUDA_TORCH_ONLY_FIX.zip" -C H:\EZScore_v1

php tests\runtime_cuda_r24_5_contract.php

powershell -ExecutionPolicy Bypass -File .\scripts\prepare_analysis_runtime.ps1
```

Attendu :

```text
TORCH= 2.14.0+cu132
TORCH CUDA= 13.2
CUDA= True
GPU= NVIDIA GeForce RTX 2060
STEM_RUNTIME_OK
[OK] EZScore_v1 STEM runtime ready.
```

Puis :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\test_analysis_worker_desktop.ps1
```

La fenêtre Windows autonome `EZScore Analysis Worker` doit apparaître.

Aucune migration Doctrine.
Aucun changement du moteur STEMS.
