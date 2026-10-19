# Directives Agents IA — Woo Flexible Shipping Table Rate (`woo-flexible-shipping`)

## 1. Contexte & Rôle du Plugin

**Woo Flexible Shipping Table Rate** est une extension propriétaire in-house **SOYOO** (`https://github.com/SOYOO974/woo-flexible-shipping/`) conçue pour remplacer *Flexible Shipping PRO (Octolize)* sans abonnement ni surcharge sur les boutiques WooCommerce gérées par l'agence (`jardin-naturel.re`, `conforama.re`, `baches-mfm.com`, etc.).

### Architecture des fichiers clés
- `woo-flexible-shipping.php` : Point d'entrée, déclaration HPOS, constante `WFS_VERSION` et initialisation de **Plugin Update Checker v5.6**.
- `src/Shipping/CalculationEngine.php` : Moteur de calcul des paliers (`item`, `weight`, `price`/`value`/`subtotal`) et gestion de l'exclusion/neutralisation chirurgicale des codes promo et bons de fidélité (`apply_all`, `ignore_patterns`, `ignore_all`).
- `src/Shipping/TableRateMethod.php` : Méthode de livraison native autonome (`soyoo_table_rate`) avec seuil de livraison gratuite (`method_free_shipping`), logo, gestion TVA incluse/exclue et matrice de règles.
- `src/Shipping/FlatRateExtension.php` : Extension greffée sur les méthodes `flat_rate` natives de WooCommerce.
- `src/Shipping/OrderMetadata.php` : Traçabilité des calculs et des coupons neutralisés dans les métadonnées de commande (`fs_costs` et `_table_rate_costs`).
- `src/Migration/AdminMigrationPage.php` & `Migrator.php` : Page d'administration des règles globales de codes promo et assistant de migration 1-clic depuis *Flexible Shipping PRO*.
- `languages/woo-flexible-shipping-fr_FR.po` & `.mo` : Traductions françaises compilées.

---

## 2. Protocole Obligatoire de Release & Mise à Jour Automatique (GitHub PUC)

### Pourquoi un simple `git push` ou `git tag` NE SUFFIT PAS
Dans `woo-flexible-shipping.php`, le vérificateur de mise à jour (`plugin-update-checker` v5.6) est configuré avec :
```php
$wfs_update_checker->setBranch( 'main' );
$wfs_update_checker->getVcsApi()->enableReleaseAssets();
```
L'appel à **`enableReleaseAssets()`** oblige WordPress à interroger les **GitHub Releases** officielles du dépôt `SOYOO974/woo-flexible-shipping` et à télécharger l'archive **`woo-flexible-shipping.zip`** attachée en tant que *Release Asset*.

> **RÈGLE STRICTE** : Toute évolution ou correction du plugin doit obligatoirement se conclure par la publication d'une **GitHub Release** complète contenant l'asset `woo-flexible-shipping.zip`. Ne jamais s'arrêter à un simple commit ou tag Git.

---

### Checklist Séquentielle de Release (6 étapes obligatoires)

#### Étape 1 — Vérification syntaxique PHP (`php -l`)
Vérifier qu'aucun fichier PHP modifié ne contient d'erreur de syntaxe :
```powershell
Get-ChildItem -Path src, woo-flexible-shipping.php -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

#### Étape 2 — Mise à jour et compilation des traductions (`.po` $\rightarrow$ `.mo`)
Si des chaînes traductibles (`__()`, `_e()`, `esc_html__()`, `esc_attr__()`) ont été ajoutées ou modifiées :
1. Mettre à jour `languages/woo-flexible-shipping-fr_FR.po` et `languages/woo-flexible-shipping.pot`.
2. Compiler impérativement le fichier binaire **`languages/woo-flexible-shipping-fr_FR.mo`** (WordPress ne lit que le `.mo` à l'exécution).

#### Étape 3 — Incrémentation de la version (3 emplacements)
Aligner systématiquement le nouveau numéro de version `X.Y.Z` aux **3 endroits suivants** :
1. `woo-flexible-shipping.php` $\rightarrow$ Entête ` * Version: X.Y.Z`
2. `woo-flexible-shipping.php` $\rightarrow$ Constante `define( 'WFS_VERSION', 'X.Y.Z' );`
3. `readme.txt` $\rightarrow$ `Stable tag: X.Y.Z` + ajout de l'entrée détaillée sous `== Changelog ==`

#### Étape 4 — Commit, Push `main` et Tag Git `vX.Y.Z`
```powershell
git add -A
git commit -m "Release vX.Y.Z: [Résumé clair des changements]"
git push origin main
git tag vX.Y.Z
git push origin vX.Y.Z
```

#### Étape 5 — Génération de l'archive POSIX `woo-flexible-shipping.zip`
- **Interdiction d'utiliser `Compress-Archive` de PowerShell** : il génère des séparateurs antislash `\` Windows ou omet le dossier racine `woo-flexible-shipping/`, ce qui corrompt l'extraction de la mise à jour sur les serveurs Linux (Rocket.net, Cloudways, etc.).
- Toujours générer le `.zip` via Python avec des slashs POSIX `/` et le dossier parent `woo-flexible-shipping/` à la racine de l'archive, en excluant `.git`, `.github`, `.gitignore`, `tests` et `AGENTS.md` :

```python
import os, zipfile, tempfile

plugin_dir = r"c:\Antigravity\woo-plugins\woo-flexible-shipping"
zip_path = os.path.join(tempfile.gettempdir(), "woo-flexible-shipping.zip")

exclude_dirs = {".git", ".github", "node_modules", "tests"}
exclude_files = {".gitignore", ".DS_Store", "woo-flexible-shipping.zip", "AGENTS.md"}

with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
    for root, dirs, files in os.walk(plugin_dir):
        dirs[:] = [d for d in dirs if d not in exclude_dirs]
        for file in files:
            if file in exclude_files:
                continue
            full_path = os.path.join(root, file)
            rel_path = os.path.relpath(full_path, plugin_dir).replace(os.sep, "/")
            zf.write(full_path, f"woo-flexible-shipping/{rel_path}")

print(f"[OK] Archive prête : {zip_path} ({os.path.getsize(zip_path)} octets)")
```

#### Étape 6 — Publication de la GitHub Release avec l'asset `.zip` via `gh` CLI
Publier la release GitHub sur le tag `vX.Y.Z` en joignant impérativement le fichier `woo-flexible-shipping.zip` :
```powershell
gh release create vX.Y.Z "$env:TEMP\woo-flexible-shipping.zip" --repo SOYOO974/woo-flexible-shipping --title "Release vX.Y.Z - [Titre]" --notes "[Notes de version détaillées]"
```

Enfin, contrôler la release publiée :
```powershell
gh release view vX.Y.Z --repo SOYOO974/woo-flexible-shipping
```
Vérifier que la sortie affiche bien **`asset: woo-flexible-shipping.zip`** et que la release n'est ni en `draft` ni en `prerelease`.
