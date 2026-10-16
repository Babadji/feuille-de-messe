#!/usr/bin/env python3
"""Fabrique les deux .docx de référence utilisés par tests/lancer.php.

Le contenu est INVENTÉ : noms de familles, intentions et horaires ne
correspondent à aucune feuille réelle. Le dépôt étant public, on n'y verse
pas de feuille de messe authentique, où figurent les noms des paroissiens.

Seule la STRUCTURE compte : c'est elle que l'analyse doit savoir lire.
"""
import zipfile, html, os

def docx(chemin, paragraphes):
    W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
    corps = []
    for texte, centre in paragraphes:
        ppr = '<w:pPr><w:jc w:val="center"/></w:pPr>' if centre else ''
        runs = ''
        for i, bout in enumerate(texte.split('\t')):
            if i:
                runs += '<w:r><w:tab/></w:r>'
            if bout:
                runs += '<w:r><w:t xml:space="preserve">%s</w:t></w:r>' % html.escape(bout)
        corps.append('<w:p>%s%s</w:p>' % (ppr, runs))
    doc = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           '<w:document xmlns:w="%s"><w:body>%s</w:body></w:document>' % (W, ''.join(corps)))
    rels = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/'
            'relationships/officeDocument" Target="word/document.xml"/></Relationships>')
    types = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
             '<Default Extension="xml" ContentType="application/xml"/>'
             '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-'
             'officedocument.wordprocessingml.document.main+xml"/></Types>')
    os.makedirs(os.path.dirname(chemin), exist_ok=True)
    with zipfile.ZipFile(chemin, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml', types)
        z.writestr('_rels/.rels', rels)
        z.writestr('word/document.xml', doc)

# ---------------------------------------------------------------- ancien
# Lecture linéaire : coordonnées, intentions, fêtes, annonces, puis la ligne
# centrée « Paroisse … 19 et 20 septembre 2026 », puis la messe.
ANCIEN = [
    ('', 0), ('Paroisse St Exemple :', 0),
    ('Mail : paroisse@exemple.fr ;', 0), ('site : https://exemple.fr/', 0),
    ('Curé-administrateur de la paroisse : Abbé Jean EXEMPLE', 0),
    ('Tél : 00 00 00 00 00', 0), ('', 0),
    ('Intentions de messe de ce dimanche : ', 0),
    ('Famille PREMIÈRE', 0), ('Pour les défunts', 0),
    ('et pour tous les paroissiens', 0), ('', 0), ('', 0),
    ('Fêtes de la semaine : le 21 septembre St Mathieu, apôtre … ; le 22 St Maurice, martyr ; '
     'le 23 St Padre Pio au 20ème', 0),
    ('', 0), ('', 0), ('Annonces de la semaine', 1), ('', 0),
    ('Lundi 21 \tSt Matthieu\t 18 h 30 messe au bourg', 0),
    ('Mardi 22\t7h-8h adoration/confession\t15h30 chapelet', 0),
    ('\t11 h 30 messe au village', 0),
    ('Mercredi 23\t11 h 30 messe\t\tcatéchisme à 14 h', 0),
    ('Dimanche 27\t26ème D. du temps de l’Eglise\t\t9h55 chapelet', 0),
    ('\t10 h 30 messe au bourg ; pour tous les paroissiens', 0), ('', 0),
    ('Paroisse St Exemple 19 et 20 septembre 2026', 1),
    ('25ème dimanche du temps de l’Eglise', 1), ('', 0),
    ('CHANT D’ENTREE : Peuple de baptisés', 0),
    ('R.Peuple de baptisés, marche vers ta lumière', 0),
    ('1.Notre Père nous aime avec tendresse', 0), ('', 0),
    ('PREPARATION PENITENTIELLE : Je confesse à Dieu tout-puissant …', 0),
    ('Kyrie : (messe de saint Exemple)', 0),
    ('Kyrie eleison, Kyrie eleison, Kyrie eleison.', 0),
    ('Gloria : (messe de saint Exemple)', 0), ('Gloria in excelsis Deo', 0),
    ('1ère lecture : Livre du prophète Isaïe (55, 6-9)', 0),
    ('Ps 144 : « Proche est le Seigneur de ceux qui l’invoquent »', 0),
    ('2ème lecture : Lettre de st Paul aux Philippiens (1, …)', 0),
    ('Acclamation de l’Evangile (Schütz)', 0), ('Alleluia, alleluia…', 0),
    ('Evangile selon Saint Mathieu (20, 1-16)', 0),
    ('CREDO : Symbole des Apôtres.', 0),
    ('P. universelle : Notre Père, nous te supplions humblement.', 0),
    ('OFFERTOIRE : orgue … offrons notre vie au Seigneur.', 0),
    ('PRIERE SUR LES OFFRANDES : Le prêtre : Priez, frères et sœurs … ', 0),
    ('tous : Que le Seigneur reçoive de vos mains ce sacrifice', 0),
    ('SANCTUS : (messe de saint Exemple)', 0),
    ('Sanctus, sanctus, sanctus, Deus sabaoth', 0),
    ('Anamnèse : (saint Exemple)', 0), ('Proclamons le mystère de la foi !', 0),
    ('Agnus Dei (messe saint Exemple)', 0),
    ('Agnus Dei, qui tollis peccata mundi.', 0),
    ('COMMUNION : ', 0),
    ('R/ : Venez approchons-nous de la table du Christ,', 0),
    ('1. La Sagesse de Dieu a préparé son vin,', 0),
    ('Sainte Vierge : Je vous salue Marie comblée de grâce …', 0),
    ('Envoi : Rendons gloire à notre Dieu.', 0),
    ('Ref : Rendons gloire à notre Dieu ! Lui qui fit des merveilles', 0),
    ('1 – Louons notre Seigneur\tCar grande est sa puissance', 0), ('', 0),
    ('Saint Exemple sera fêté le dimanche suivant, messe célébrée à 10 h 30', 1),
    ('Pour bien préparer cette solennité, prions chaque jour. Merci !', 1),
]

# ---------------------------------------------------------------- livret
# Imposé pour l'impression : la FIN de la messe arrive en premier, puis les
# infos pratiques, puis le titre, puis le DÉBUT de la messe. Le titre ne porte
# qu'une seule date, et les zones de texte Word dupliquent ses lignes.
LIVRET = [
    ('3. Voici l’aurore d’un jour nouveau', 0),
    ('Bénédiction puis …', 0),
    ('Sainte Vierge : Je vous salue Marie comblée de grâce …', 0),
    ('Envoi : Rendons gloire à notre Dieu.', 0),
    ('Ref : Rendons gloire à notre Dieu !', 0),
    ('1 – Louons notre Seigneur\tCar grande est sa puissance', 0), ('', 0),
    ('Paroisse St Exemple : Mail : paroisse@exemple.fr ;', 0),
    ('site : https://exemple.fr/ Curé : Abbé Jean EXEMPLE', 0),
    ('Tél : 00 00 00 00 00', 0), ('', 0),
    ('Intentions de messe de ce dimanche :  pour tous les paroissiens', 0), ('', 0),
    ('Annonces de la semaine', 0), ('', 0),
    ('Mardi 20\t15h30 chapelet au village', 0),
    ('Vendredi 23\t17 h 30 adoration/confessions\t18 h 30 messe', 0),
    ('Samedi 24\tbibliothèque au presbytère', 0),
    ('18 h 30 messe au village pour le renouveau de la foi', 0),
    ('Dimanche 25\tfête de la dédicace de l’église\t9h55 chapelet', 0),
    ('\t10 h 30 messe au bourg pour tous les paroissiens', 0), ('', 0),
    ('St Exemple, priez pour nous +', 0), ('', 0),
    # Ligne produite par une zone de texte : tout le titre concaténé deux fois.
    ('Fête de St ExempleL’évêque installe le nouveau curé – 18 octobre 2026'
     'Fête de St ExempleL’évêque installe le nouveau curé – 18 octobre 2026', 1),
    ('Fête de St Exemple', 1),
    ('L’évêque installe le nouveau curé – 18 octobre 2026', 1), ('', 0),
    ('Fête de St Exemple', 1),
    ('L’évêque installe le nouveau curé – 18 octobre 2026', 1), ('', 0), ('', 0),
    ('CHANT D’ENTREE : ', 0),
    ('C’est toi qui nous appelles, Seigneur Jésus,', 0),
    ('3 - Tu invites tes disciples à marcher jusqu’à la croix', 0), ('', 0),
    ('PREPARATION PENITENTIELLE : Je confesse à Dieu tout-puissant …', 0), ('', 0),
    ('Kyrie : (messe de saint Exemple)', 0),
    ('Kyrie eleison, Kyrie eleison, Kyrie eleison. ', 0), ('', 0),
    ('PRESENTATION par l’évêque et le conseil paroissial', 0), ('', 0),
    ('Gloria : (messe de saint Exemple)', 0),
    ('Gloria, gloria, in excelsis Deo ! (bis)', 0),
    ('1ère lecture : de Ben Sira le Sage (2, 7-11)', 0), ('', 0),
    ('Ps 33 : « Goûtez et voyez comme est bon le Seigneur »', 0), ('', 0),
    ('2ème lecture : Lettre de st Paul aux Philippiens (3, 8-14)', 0), ('', 0),
    ('Acclamation de l’Evangile (messe de St Exemple)', 0),
    ('Alleluia, alleluia, alleluia…', 0),
    ('Evangile selon Saint Mathieu (7, 21-27)        puis après la lecture', 0),
    ('CREDO : L’évêque : proclamez maintenant la Foi de l’Eglise', 0),
    ('P.U. : Esprit de Dieu intercède pour nous', 0),
    ('OFFERTOIRE : orgue … offrons notre vie au Seigneur.', 0),
    ('PRIERE SUR LES OFFRANDES : Le prêtre : Priez, frères et sœurs … ', 0),
    ('Tous : Que le Seigneur reçoive de vos mains ce sacrifice', 0),
    ('SANCTUS : (messe de saint Exemple)', 0),
    ('Sanctus, sanctus, sanctus, Dominus Deus sabaoth (bis)', 0),
    ('Anamnèse : (saint Exemple)   \t', 0),
    ('Proclamons le mystère de la foi !', 0),
    ('Agnus Dei (messe de saint Exemple)', 0),
    ('Agnus Dei, qui tollis peccata mundi. Miserere nobis. ', 0),
    ('COMMUNION : ', 0),
    ('R/ : Venez approchons-nous de la table du Christ,', 0),
    ('1. La Sagesse de Dieu a préparé son vin,', 0),
    # Le curé sépare parfois par une ligne de tirets centrée : elle ne doit
    # pas être prise pour un encart d'annonce.
    ('-----------------------------------------', 1),
    ('1 - Oh, prends mon âme, prends-la, Seigneur', 0),
]

ici = os.path.dirname(os.path.abspath(__file__))
docx(os.path.join(ici, 'jeux-essai', 'ancien-format.docx'), ANCIEN)
docx(os.path.join(ici, 'jeux-essai', 'livret-2026.docx'), LIVRET)
print('jeux d’essai écrits dans tests/jeux-essai/')
