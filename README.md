# API NaturAfrica (KLCD)

Auteur: Dounya HAMMA

Ce projet contient les éléments nécessaires pour :

- créer et initialiser la base de données **NaturAfrica (KLCD)** sous PostgreSQL ;
- parser les données fournies par le client (XLSM -> CSV -> JSON) ;
- exposer ces données via une **API PHP** simple ;
- fournir une base technique pour une future intégration dans le KLCD Viewer (https://klcdviewer.visioterra.fr/).

---

## 1. Contenu du projet

---

### Schéma de base de données

- **BDD_NaturAfrica.sql**  
  Script SQL complet contenant les instructions `CREATE TABLE` pour :

  - Tables principales :  
    `KLCD`, `Location`, `ProtectedArea`, `Action`, `EU_Programme`,  
    `Institution`, `MemberState`, `Pillar`

  - Tables métiers :  
    `ActivitySector`, `Activity`

  - Tables de liaison :  
    `KLCD_Location`, `ProtectedArea_KLCD`, `Action_KLCD`,  
    `Action_Location`, `Action_ProtectedArea`,  
    `Action_Implementer`, `Action_MS_Funding`, `Action_Activity`, etc.

Le modèle suit la note **NaturAfrica_DB_Note_modele_donnees** (structure normalisée, extensible).

---

## 2. Pré-requis

### Logiciels

- **PostgreSQL** 17 ou 18
- **pgAdmin 4** (facultatif mais recommandé)
- **PHP** 8+ avec :
  - extension `pdo_pgsql`
  - extension `pgsql`
- **Python 3** + `pandas` + `openpyxl` pour retravailler le XLSM vers CSV.

### Vérifier les extensions PHP

Dans un terminal:

```bash
php -m
```

Sinon, éditer le fichier php.ini (trouvé via php --ini) et décommenter:

```
extension=pdo_pgsql
extension=pgsql
```

## 3. Installation de PostgreSQL et création de la base

### 3.1 Création de la base naturafrica

```
psql -U postgres -c "CREATE DATABASE naturafrica;"
```

### 3.2 Import du schéma SQL

```
psql -U postgres -d naturafrica -f BDD_NaturAfrica.sql
```

En cas de succès, vous verrez :

```
CREATE TABLE
CREATE TABLE
...
```
Pour une importation automatique de la base:
```
php import_db.php
```

## 4. Récapitulatif des commandes

Créer la base:

```bash
psql -U USER -c "CREATE DATABASE naturafrica;"
```

Connexion à la base:

```bash
php connect_db.php
```

Import SQL:

```bash
psql -U USER -d naturafrica -f BDD_NaturAfrica.sql
php import_db.php
```

Se rendre dans la base de données locale:

```bash
$env:PGHOST="localhost"
$env:PGPORT="5432"
$env:PGDATABASE="naturafrica"
$env:PGUSER="USER"
$env:PGPASSWORD="PASSWORD"
```

Ajouter les données dans la base:

```bash
python .\import_core_excel_v2.py .\20260204_Jungers__NaturAfrica_DB_core_v2.xlsx
```

Lancer serveur PHP:

```bash
php -S localhost:8000
```

## 7. Contact

- Développé par : Dounya HAMMA (dounya.hamma@visioterra.fr)
- Encadrants : Kévin GROSS (kevin.gross@visioterra.fr), Zhour NAJOUI (zhour.najoui-nafai@visioterra.fr), Serge RIAZANOFF (serge.riazanoff@visioterra.fr)
- Projet : NaturAfrica / KLCD