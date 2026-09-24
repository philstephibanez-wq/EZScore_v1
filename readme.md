# EZScore_v1 — hover global piloté par JS

Base vérifiée sur le HEAD GitHub :

```text
5ebf8b484159c49dbf4a6d3f5889e7b3def75014
```

Le CSS `:hover` seul n'a pas donné de résultat fiable sur le dashboard dans l'environnement courant.

Ce correctif ajoute un gestionnaire global `interaction-feedback.js` qui applique explicitement une classe `is-ui-hover` à tout contrôle interactif sous le pointeur.

Sont couverts automatiquement :

```text
a[href]
button
input submit/button
checkbox/radio
select
summary
label[for]
[role="button"]
```

Le dashboard reçoit donc le même feedback que l'administration, sans règle par page.

## Installation

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_GLOBAL_HOVER_JS_FIX.zip" -C H:\EZScore_v1
php bin\console cache:clear
php bin\console lint:twig templates
```

Aucune migration.
