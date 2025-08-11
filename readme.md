# effi Post Redate

**Contributors:** Cédric GIRARD  
**Tags:** date, articles, publication, redater, outils  
**Requires at least:** 5.0  
**Tested up to:** 6.6  
**Requires PHP:** 7.4  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

## Description

**effi Post Redate** est un plugin WordPress qui vous permet de **redistribuer les dates de publication** de vos articles publiés entre deux dates définies, de façon **progressive**.  
Idéal pour le lancement d’un site où plusieurs articles sont publiés en même temps, afin de leur attribuer des dates espacées et réalistes.

- Ajoute un onglet **"Redater les articles"** dans le menu **Outils** de l’administration WordPress.
- Choisissez une **date de début** et une **date de fin** (par défaut, la fin = aujourd’hui).
- Les dates sont **réparties linéairement** entre ces bornes selon l’ordre choisi.
- Fonctionne sur les **articles** mais aussi sur tout autre type de contenu public.
- **Mode test** (aperçu) pour vérifier le résultat avant application.

## Fonctionnalités

- Sélection de la **date de début** et **date de fin**  
- Choix du **type de contenu** (article, page ou autre CPT public)  
- Ordre de référence : du plus ancien au plus récent ou l’inverse  
- Option pour **exclure les articles épinglés**  
- Limitation au **nombre d’articles** à traiter  
- **Mode test** (aperçu sans enregistrement)  
- Affichage d’un **échantillon des nouvelles dates** pour vérification  
- Respect du fuseau horaire du site  

## Captures d’écran

1. **Formulaire** dans Outils → Redater les articles
2. **Aperçu** des dates calculées en mode test
3. **Confirmation** après application des modifications

## Installation

1. Téléchargez le fichier ZIP du plugin ou clonez le dépôt dans votre dossier `wp-content/plugins/`.
2. Activez le plugin dans **Extensions → Extensions installées**.
3. Allez dans **Outils → Redater les articles**.
4. Sélectionnez vos paramètres et lancez un **mode test** avant d’appliquer.

## Utilisation

1. Ouvrez le menu **Outils → Redater les articles**.
2. Choisissez :
   - Date de début
   - Date de fin (aujourd’hui par défaut)
   - Type de contenu
   - Ordre des articles
   - Options supplémentaires (exclure épinglés, limiter le nombre)
3. Lancez en **mode test** pour vérifier l’aperçu.
4. Décochez **Mode test** pour appliquer réellement les nouvelles dates.

## Changelog

### 1.0.0
- Version initiale
- Redistribution progressive des dates entre deux bornes
- Mode test avec aperçu
- Filtrage par type de contenu et options diverses

## Licence

Ce plugin est distribué sous licence GPLv2 ou ultérieure.  
Vous êtes libre de l’utiliser, le modifier et le redistribuer, sous réserve de respecter la licence.

