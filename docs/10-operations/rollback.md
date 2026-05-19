# Rollback

Tout rollback doit tenir compte :

- du code deploye
- des migrations deja appliquees
- des donnees creees ou deplacees
- des workers asynchrones en cours

Une migration irreversible doit le dire explicitement dans sa description.
