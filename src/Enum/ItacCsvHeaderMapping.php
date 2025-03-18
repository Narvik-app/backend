<?php

namespace App\Enum;

enum ItacCsvHeaderMapping: string {
  case CIVILITY = 'Civilité'; // M.
  case GENDER = 'Sexe'; // M
  case LICENCE = 'N° Licence';
  case LASTNAME = 'Nom';
  case FIRSTNAME = 'Prénom';
  case BIRTHDAY = 'Date de naissance';
  // case HANDISPORT = 'Handisport';


  case POSTAL_1 = 'Adresse 1';
  case POSTAL_2 = 'Adresse 2';
  case POSTAL_3 = 'Adresse 3';
  case ZIP_CODE = 'Code Postal';
  case CITY = 'Ville';
  case COUNTRY = 'Pays';


  case EMAIL = 'Email';
  case PHONE = 'Téléphone';
  case MOBILE_PHONE = 'Téléphone portable';


  case BLACKLISTED = 'Etat (Blacklistage)'; // Autorisé
  case SEASON = 'Saison';
  case AGE_CODE = 'Code catégorie d\'âge'; // S3
  case AGE_CATEGORY = 'Catégorie d\'âge'; // Senior 3
}
