Nouveau boostrap

Fichiers à consulter :
C:\wamp64\www\up-animation\assets\scss\front\bootstrap\*
C:\wamp64\www\up-animation\assets\js\front\*
C:\wamp64\www\up-animation\templates\front\*

Contexte :
J'ai mis à jour la version boostrap du front.

Objectif:
Comme dans l'ancienne version il faudra faire en sorte d'utiliser uniquement ce dont on a besoin. CAD il faut consulter le ficher C:\wamp64\www\up-animation\assets\scss\front\default\variables-bootstrap.scss
ou des variables sont instanciées pour faire appel aux modules si nécessaire. ex $enable-carousel si besoin de carousel. Il faut donc regarder tout les $enable- et mettre des conditions dans les fichiers scss de bootstrap pour ne pas charger le css inutile pour le projet.
Il faudra églamement vérifier que l'inclusion de boostrap est correctement faite coté front

J'ai également mis à jour le js boostrap front. Il faut checker s'il est toujours fonctionnel.