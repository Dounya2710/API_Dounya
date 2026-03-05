# Mapping des données KLCD (JSON) vers le schéma PostgreSQL

Ce document décrit comment les champs du fichier `klcd_data.json` sont mappés vers les tables du schéma PostgreSQL NaturAfrica / KLCD.  
Il sert de base de discussion avec les clients et l’équipe VisioTerra pour valider les choix d’intégration.

---

## 1. Rappel du schéma SQL concerné

Les tables principalement concernées par ce fichier JSON sont :

- `Action`
- `MemberState`
- `Institution`
- `Pillar`
- `ActivitySector`
- `Activity`
- `Action_Activity`
- `Action_Implementer`
- `Action_MS_Funding`

Les tables suivantes **ne peuvent pas encore être renseignées** avec ce JSON uniquement (il manque des informations spécifiques) :

- `KLCD`
- `Location`
- `ProtectedArea`
- `KLCD_Location`
- `ProtectedArea_KLCD`
- `Action_Location`

Elles sont prévues pour des données plus détaillées (zones KLCD, aires protégées, localisation fine) qui viendront probablement d’autres fichiers.

---

## 2. Structure générale du JSON

Chaque entrée du fichier `klcd_data.json` correspond à **une action / un projet**.  
On y trouve notamment :

- des informations de base sur le projet (titre, budget, dates, portée…),
- le pays leader,
- le(s) bailleur(s) (`Donor`),
- l’opérateur (`Operator`),
- des codes/labels d’activités pour trois piliers :  
  - `Conservation`  
  - `Green Economy`  
  - `Governance`

---

## 3. Mapping détaillé champ par champ

### 3.1. Table `Action`

| Champ JSON             | Description                               | Table.colonne                 | Remarques                      |
|------------------------|-------------------------------------------|-------------------------------|--------------------------------|
| `Contract title`       | Titre du projet / contrat                 | `Action.title`                | Copie directe (trim éventuel). |
| `Funding (M€)`         | Budget en millions d’euros                | `Action.total_budget_EUR`     | À convertir en nombre ; si l’on souhaite des euros, multiplier par 1e6. |
| `Starting date`        | Date de début du projet                   | `Action.start_date`           | À parser en `DATE` (format à confirmer : `YYYY-MM-DD` ou autre). |
| `Ending date`          | Date de fin du projet                     | `Action.end_date`             | Idem `start_date`.             |
| `Scope`                | Portée (KLCD, régional, etc.)             | (option) `Action.note` ou champ dédié | À clarifier avec les clients : garder sous forme de texte libre ou normaliser. |
| `Contract ID` / équiv. | Identifiant contrat (si présent)          | `Action.opsys_contract_id`    | À confirmer selon les colonnes disponibles dans le JSON. |
| `Conservation`         | Liste de codes/labels d’activités C*      | via `Action_Activity`          | Voir section 3.4.             |
| `Green Economy`        | Liste d’activités E*                      | via `Action_Activity`         | Voir section 3.4.              |
| `Governance`           | Liste d’activités G*                      | via `Action_Activity`         | Voir section 3.4.              |

Les flags `biodiversity_flag`, `green_economy_flag`, `governance_flag` dans `Action` peuvent être dérivés automatiquement :

- `biodiversity_flag = TRUE` si au moins une activité `Conservation` (C*) est associée.
- `green_economy_flag = TRUE` si au moins une activité `Green Economy` (E*) est associée.
- `governance_flag = TRUE` si au moins une activité `Governance` (G*) est associée.

---

### 3.2. Table `MemberState` et `Action_MS_Funding`

| Champ JSON      | Description                         | Table.colonne                     | Remarques |
|-----------------|-------------------------------------|-----------------------------------|-----------|
| `Lead country`  | Pays leader de l’action (texte)     | `MemberState.ms_name`             | Insertion si non existant. |
| (dérivé)        | Code ISO2 du pays leader            | `MemberState.ms_iso2`             | À remplir via correspondance ISO (ou plus tard). |
| `Lead country`  | Référence depuis l’action           | `Action_MS_Funding.ms_id`         | Lien (role = `'lead'`). |
| `Funding (M€)`  | Budget associé                      | `Action_MS_Funding.amount_eur`    | Même conversion que pour `Action.total_budget_EUR`. |
| (dérivé)        | Rôle du pays                        | `Action_MS_Funding.role`          | Valeur recommandée : `'lead_country'`. |

S’il y a des co-financeurs au niveau États, ils pourront être ajoutés plus tard dans `Action_MS_Funding`.

---

### 3.3. Table `Institution` et `Action_Implementer`

| Champ JSON  | Description                             | Table.colonne                       | Remarques |
|-------------|-----------------------------------------|-------------------------------------|-----------|
| `Donor`     | Bailleur(s) du projet                  | `Institution.name`                  | Si plusieurs donneurs séparés par virgule/“+”, éclater en plusieurs institutions. |
|             |                                         | `Institution.type`                  | Valeur recommandée : `'donor'`. |
|             |                                         | `Action_Implementer.role`           | `'donor'`. |
|             |                                         | `Action_Implementer.action_id`      | Lien vers l’action. |
| `Operator`  | Opérateur(s) du projet                 | `Institution.name`                  | Éclater si liste. |
|             |                                         | `Institution.type`                  | Valeur recommandée : `'operator'`. |
|             |                                         | `Action_Implementer.role`           | `'operator'` ou `'implementer'`. |
|             |                                         | `Action_Implementer.action_id`      | Lien vers l’action. |

La colonne `iso3` d’`Institution` pourra être renseignée ultérieurement si nécessaire (par exemple pour des institutions nationales).

---

### 3.4. Tables `Pillar`, `ActivitySector`, `Activity`, `Action_Activity`

Les champs `Conservation`, `Green Economy`, `Governance` contiennent des listes de chaînes de la forme :

- `C1 - Patrolling and surveillance`
- `E13 - Ecotourism value chain...`
- `G5 - Participative governance...`

#### 3.4.1. Table `Pillar`

Pré-remplissage recommandé :

| pillar_code | pillar_name      |
|-------------|------------------|
| `C`         | Conservation     |
| `E`         | Green Economy    |
| `G`         | Governance       |

#### 3.4.2. Table `Activity` (et éventuellement `ActivitySector`)

Pour chaque entrée `PiX - Label` (avec `P` ∈ {`C`,`E`,`G`} et `iX` = entier) :

- `activity_id` : le code complet, ex. `C1`, `E13`, `G5`
- `activity_label` : la partie texte après le tiret, ex. `Patrolling and surveillance`
- `sector_id` :  
  - soit égal à `activity_id` si l’on ne distingue pas “secteurs” et “activités”,  
  - soit une autre clé si la liste officielle des secteurs est fournie par les clients.

Exemple :

| Colonne              | Valeur                              |
|----------------------|-------------------------------------|
| `Activity.activity_id`      | `C1`                                |
| `Activity.activity_label`   | `Patrolling and surveillance`      |
| `Activity.sector_id`        | `C1` *(proposition simple)*        |
| `ActivitySector.sector_id`  | `C1`                                |
| `ActivitySector.pillar_code`| `C`                                |
| `ActivitySector.sector_label` | `Patrolling and surveillance`   |

#### 3.4.3. Table `Action_Activity`

Pour chaque action :

- Parcourir les listes `Conservation`, `Green Economy`, `Governance`.
- Extraire `activity_id` (ex. `C1`, `E13`) et créer une ligne :

| Colonne                  | Valeur                                  |
|--------------------------|-----------------------------------------|
| `Action_Activity.action_id`   | ID de l’action courante               |
| `Action_Activity.activity_id` | ID d’activité `C*`, `E*`, `G*`        |
| `Action_Activity.is_primary`  | `TRUE` pour les activités principales (si notion fournie), sinon `FALSE` par défaut |

---

## 4. Tables non alimentées par ce JSON

Les tables ci-dessous restent vides à ce stade, faute d’informations correspondantes dans `klcd_data.json` :

- `KLCD` : nécessite des identifiants et des noms de paysages / complexes KLCD, superficies, densité de population, etc.
- `Location` : nécessite des codes administratifs (ISO3, niveaux admin, noms de régions, etc.).
- `ProtectedArea` : nécessite des identifiants WDPA, catégorie IUCN, surfaces reportées / SIG, statut, etc.
- `KLCD_Location`, `ProtectedArea_KLCD`, `Action_Location` : nécessitent des relations explicites entre actions, zones KLCD, aires protégées et entités géographiques.

Ces tables seront remplies à partir d’autres sources (fichiers complémentaires ou couches SIG), une fois fournies / validées par les clients.

---

## 5. Points à valider avec les clients

1. **Interprétation de `Scope`** :  
   - Doit-il être stocké tel quel (texte) ou normalisé dans une table de référence ?
2. **Granularité des bailleurs / co-bailleurs** :  
   - Différence entre `Donor` institutionnel et éventuels financements par États membres ?
3. **Structure exacte des secteurs / activités** :  
   - Les codes `C1`, `E13`, `G5`, etc. constituent-ils à la fois le secteur et l’activité, ou existe-t-il une hiérarchie “secteur → activité” à respecter ?
4. **Remplissage de `MemberState.ms_iso2`** :  
   - Peut-on utiliser la correspondance standard ISO2 à partir du nom de pays présent dans le JSON ?
5. **Priorisation des champs** :  
   - Quelles colonnes sont indispensables pour une première version de la base exploitable par les utilisateurs (back-office / application) ?

---

## 6. Prochaines étapes techniques

1. Adapter le script de parsing Python pour :
   - lire `klcd_data.json`,
   - produire des CSV intermédiaires alignés sur les tables (`actions.csv`, `institutions.csv`, `activities.csv`, `action_activities.csv`, etc.),
   - préparer l’import dans PostgreSQL via `COPY` ou `INSERT`.

2. Soumettre ce document de mapping à l’équipe pour validation avant d’industrialiser le chargement des données.
