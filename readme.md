# EZScore_v1 — hover global cartes/liens interactifs

Correctif ciblé sur les composants interactifs qui sont des `<a>` et non des `<button>`.

Le dashboard utilise :

```html
<a class="module-card">...</a>
```

Il n'était donc pas couvert par les règles précédentes.

Ajouts :
- `a.module-card`
- `a.song-tile`
- `.dashboard-grid a`
- `.card-grid a`

Effet :
- légère élévation ;
- halo bleu ;
- fond éclairci ;
- bordure renforcée.

Le fichier `interactions.css` est cache-busté en `20260924b`.

Installation :

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_GLOBAL_HOVER_CARDS_FIX.zip" -C H:\EZScore_v1
php bin\console cache:clear
php bin\console lint:twig templates
```

Aucune migration.
