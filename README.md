# API NaturAfrica (KLCD)

Auteur: Dounya HAMMA

Ce projet contient les éléments nécessaires pour :

- créer et initialiser la base de données **NaturAfrica (KLCD)** sous PostgreSQL ;
- parser les données fournies par le client (XLSM -> CSV -> JSON) ;
- exposer ces données via une **API PHP** simple ;
- fournir une base technique pour une future intégration dans le KLCD Viewer (https://klcdviewer.visioterra.fr/).

---

## 1. Contenu du projet

### 1.1 Scripts PHP

- **connect_db.php**  
  Gère la connexion à PostgreSQL avec PDO (fonction `get_naturafrica_pdo()`).

- **import_db.php**  
  Lit et exécute le script SQL `BDD_NaturAfrica.sql` sur la base `NaturAfrica`.  
  Utile si l’on veut (ré)initialiser la base à partir du schéma.

- **klcd_from_excel.php**  
  Lit le fichier CSV `Fiches_NA_DB_24_v5b.csv` et renvoie les données au format **JSON**.  
  Utilisé pour tester un flux simple CSV vers JSON avant l’intégration BDD.

---

### 1.2 Schéma de base de données

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

### 1.3 Données et documents

- **Fiches_NA_DB_24_v5b.xlsm**  
  Fichier Excel source (macro) fourni par le client, contenant les fiches KLCD.

- **Fiches_NA_DB_24_v5b.csv**  
  Version CSV des fiches (encodage UTF-8, séparateur `;`).  
  Utilisée par `klcd_from_excel.php`.

- **Fiches_NA_DB_24_v5b.pdf**  
  Version PDF des fiches (référence visuelle).

- **parse_file.py**  
  Script Python permettant de convertir le XLSM en CSV propre et UTF-8 (en particulier : ignorer les lignes/colonnes de diagrammes, ne garder que les données utiles).

- **NaturAfrica_DB_Note_modele_donnees.docx**  
  Document décrivant le modèle relationnel NaturAfrica.

- Une documentation technique est accessible via: /disco3/VisioTerra/technique/P372_AGRECO_B4LIFE/engineering/VT-P372-DOC-005-F-01-00_KLCD_Viewer_draft02.docx

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

## 4. API CSV vers JSON (`klcd_from_excel.php`)

Test:

```bash
php -S localhost:8000
```

Accès:

```bash
http://localhost:8000/klcd_from_excel.php
```

## 5. Conversion XLSM vers CSV (`parse_file.py`)

Cela fait principalement: 

```python
import pandas as pd

df = pd.read_excel("Fiches_NA_DB_24_v5b.xlsm")

# Ne garder que les bonnes colonnes/lignes (à partir de ligne 31, colonne B)
df_clean = df.iloc[30:, 1:]

df_clean.to_csv("Fiches_NA_DB_24_v5b.csv", sep=";", index=False, encoding="utf-8")
```

Pour le tester:

```bash
python parse_file.py
```

## 6. Récapitulatif des commandes

Créer la base:

```bash
psql -U postgres -c "CREATE DATABASE naturafrica;"
```

Import SQL:

```bash
psql -U postgres -d naturafrica -f BDD_NaturAfrica.sql
```

Lancer serveur PHP:

```bash
php -S localhost:8000
```

## 7. Contact

- Développé par : Dounya HAMMA (dounya.hamma@visioterra.fr)
- Encadrants : Kévin GROSS (kevin.gross@visioterra.fr), Zhour NAJOUI (zhour.najoui-nafai@visioterra.fr), Serge RIAZANOFF (serge.riazanoff@visioterra.fr)
- Projet : NaturAfrica / KLCD