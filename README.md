# Feuille de messe

Plugin WordPress pour la **paroisse Saint Carlo Acutis**. On dépose le document
Word envoyé par l'abbé dans le back office, et le site en publie une version
lisible sur téléphone — sans avoir à mettre en page quoi que ce soit.

## Le parcours, chaque semaine

1. **Feuilles de messe → Déposer une feuille**, on glisse le `.docx`.
2. Le plugin analyse le document et affiche **le rendu exact**, avec le dimanche
   couvert et le temps liturgique qu'il a reconnus.
3. On publie. Le `.docx` d'origine reste joint et téléchargeable.

Rien n'est mis en ligne avant le clic sur « Publier » : entre les deux, la
feuille existe en brouillon.

Redéposer un document pour un dimanche déjà couvert **met à jour** la feuille
existante au lieu d'en créer une seconde — l'adresse ne change pas, donc le QR
code déjà imprimé reste valable.

## Ce que l'analyse sait faire

Le découpage ne s'appuie **jamais sur le gras** : dans ces feuilles, le gras est
appliqué de façon irrégulière, y compris sur des paragraphes courants. Il
s'appuie sur le vocabulaire liturgique, stable d'une semaine à l'autre.

- La ligne centrée « Paroisse … 19 et 20 septembre 2026 » sépare les annonces de
  la semaine du déroulé de la messe, et donne la date.
- Les repères (`CHANT D'ENTREE`, `Kyrie`, `1ère lecture`, `COMMUNION`…) découpent
  la messe. Un repère ne compte comme titre que s'il est suivi de « : », de « ( »
  ou de rien — sinon « Kyrie eleison… », qui est le chant lui-même, serait pris
  pour un titre.
- Les accents perdus par les capitales sont rétablis (`ENTREE` → *Entrée*).
- Les fêtes de la semaine, un pavé de texte dans le Word, deviennent une liste.
- Les annonces deviennent un horaire jour par jour.
- Refrains, couplets et réponses de l'assemblée sont distingués.

Si la mise en forme du document change un jour, le découpage se dégradera. C'est
pourquoi le `.docx` reste toujours téléchargeable, que le texte reste modifiable
dans l'éditeur WordPress, et qu'un bouton **Ré-analyser** permet de repartir du
document sans le redéposer.

## Accès rapide pour les paroissiens

- **Adresse permanente** : `/feuille` mène toujours à la feuille la plus récente.
  C'est elle qu'il faut mettre dans le QR code de l'entrée de l'église et en pied
  de la feuille papier.
- **Ordinateur** : le code court `[feuille_bouton]` s'insère dans un widget
  « Code court » d'Elementor, dans l'en-tête. Il affiche la date et disparaît
  s'il n'y a pas de feuille.
- **Téléphone** : un bouton flottant apparaît du samedi 16 h au dimanche 20 h
  (réglable). La fenêtre couvre la messe anticipée du samedi soir.

⚠️ Le moment d'affichage est calculé **à l'heure du visiteur, en JavaScript**, et
non côté serveur. Le site sert des pages mises en cache : une décision prise en
PHP (« on est dimanche ») resterait figée dans le cache les jours suivants.

## Réglages

**Feuilles de messe → Réglages** : fenêtre d'affichage du bouton, portée (tout le
site / accueil seulement / désactivé), et l'adresse permanente à copier.

## Mises à jour

Assurées par [PM Updater](https://github.com/Babadji/pm-updater), qui lit
l'en-tête `Version:` du fichier principal sur la branche `main`. Pour publier une
mise à jour : incrémenter ce numéro, commiter, pousser. Ni tag ni release.

## Pré-requis

- PHP 7.3+ avec l'extension **ZipArchive** (présente chez OVH).
- Aucune dépendance externe : un `.docx` est une archive zip contenant du XML,
  que `ZipArchive` et `DOMDocument` suffisent à lire.

## Reste à faire

- Génération du QR code depuis l'écran de réglages (aujourd'hui, l'adresse est à
  copier et le QR à produire ailleurs).
- Archive des feuilles précédentes sur la page publique.
