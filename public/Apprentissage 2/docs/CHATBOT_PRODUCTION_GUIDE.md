# Guide Production du Chatbot Educatif

## Objectif

Ce document explique comment rendre le chatbot de votre plateforme educative le plus fiable possible pour un grand nombre d'eleves de Terminale connectes en meme temps.

Il est adapte a votre projet actuel, dans lequel le chatbot passe aujourd'hui par `api/gemini.php`.

## Reponse courte

Pour qu'un chatbot web soit solide en production, il ne suffit pas de "prendre un abonnement".

Il faut mettre en place en meme temps :

1. un fournisseur API payant et dimensionne pour la production,
2. des limites de debit cote serveur,
3. une gestion stricte des tokens,
4. des retries intelligents en cas de surcharge,
5. un monitoring des couts et des erreurs,
6. une architecture serveur capable d'encaisser plusieurs utilisateurs simultanes.

Un abonnement ChatGPT classique ne suffit pas pour une application web publique. Pour une plateforme comme la votre, il faut utiliser une API de production et un projet facture a l'usage.

## Ce que j'ai observe dans votre projet actuel

### Points positifs

- Le chatbot est appele cote serveur, donc la cle API n'est pas envoyee au navigateur.
- Il y a deja un controle CSRF.
- Il y a deja un historique de conversation en base.
- Il y a deja un retry simple sur les erreurs de surcharge `503` et `529`.

### Points a corriger rapidement

1. **Cle API exposee dans le code**

Dans `includes/config.php`, la cle API est ecrite en dur. Si ce fichier a deja ete sauvegarde, partage ou versionne, il faut **regenerer la cle immediatement** et la sortir du code source.

2. **Rate limit trop faible et seulement par session**

Votre code utilise :

- `GEMINI_RATE_LIMIT = 10`
- limitation basee sur `$_SESSION`

Cela ne protege pas vraiment contre une vraie montee en charge avec beaucoup d'eleves connectes en meme temps. Il faut une limitation :

- par utilisateur,
- par IP,
- et globale pour toute l'application.

3. **Pas de file d'attente globale**

Si 200 eleves envoient un message au meme moment, votre application peut lancer trop d'appels API en parallele. Il faut ajouter une **queue** ou au minimum un **plafond de concurrence**.

4. **Le PDF n'est pas vraiment exploite**

Dans `api/gemini.php`, le contenu du document n'est pas extrait. Le bot repond donc surtout a partir du titre, du type et des metadonnees, pas a partir du contenu pedagogique reel. Pour la qualite, c'est un point majeur.

## Abonnement ou API : ce qu'il faut comprendre

### Ce qu'il faut prendre

Pour une application web avec de vrais utilisateurs, il faut prendre **l'API OpenAI** ou garder **l'API Gemini**, avec une facturation d'usage adaptee a la production.

### Ce qu'il ne faut pas confondre

- **ChatGPT Plus / Pro** : abonnement utilisateur pour utiliser ChatGPT
- **API OpenAI** : usage serveur pour votre site web

Pour votre plateforme, c'est **l'API** qui compte.

## Recommandation modele si vous passez sur OpenAI

Pour un chatbot educatif a fort trafic, je recommande :

- **Modele principal** : `gpt-5.4-mini`
- **Modele ultra-economique / fallback / classification** : `gpt-5.4-nano`
- **Modele premium pour cas difficiles** : `gpt-5.4`

### Pourquoi

Selon la documentation officielle OpenAI actuelle :

- `gpt-5.4` est le modele par defaut pour les taches importantes et complexes.
- `gpt-5.4-mini` est plus rapide et plus economique pour les gros volumes.
- `gpt-5.4-nano` est le moins cher pour les usages simples a tres grand volume.

### Prix indicatifs verifies le 21 avril 2026

- `gpt-5.4` : environ **$2.50 / 1M tokens input** et **$15 / 1M tokens output**
- `gpt-5.4-mini` : environ **$0.75 / 1M tokens input** et **$4.50 / 1M tokens output**
- `gpt-5.4-nano` : environ **$0.20 / 1M tokens input** et **$1.25 / 1M tokens output**

Ces valeurs peuvent evoluer, donc il faut toujours verifier avant mise en production.

## Strategie recommandee pour eviter la surcharge

### 1. Mettre le chatbot derriere une API serveur uniquement

C'est deja en partie le cas chez vous. Il faut conserver ce principe :

- navigateur -> votre backend PHP
- backend PHP -> API IA

Ne jamais appeler l'IA directement depuis le front.

### 2. Ajouter une limitation multi-niveaux

Il faut au minimum :

- `5` a `10` messages par minute par eleve,
- une limite par IP,
- une limite globale de concurrence, par exemple `20` ou `30` generations simultanees au depart,
- une reponse claire quand la file est pleine : "Le chatbot est tres sollicite, reessaie dans quelques secondes."

### 3. Ajouter une queue

Le plus propre est d'utiliser :

- Redis + worker
- ou une table SQL de jobs + worker cron

Principe :

1. le front envoie le message,
2. le backend cree une tache,
3. un worker traite la tache,
4. le front recupere le resultat.

Avantage : vous evitez d'ouvrir 200 appels API en meme temps.

### 4. Mettre des retries intelligents

En cas de surcharge ou de rate limit, il faut :

- retry sur `429`, `500`, `503`, `529`,
- utiliser un **exponential backoff**,
- limiter le nombre de retries,
- logger chaque echec.

Exemple simple :

- essai 1
- attente 1s
- essai 2
- attente 2s
- essai 3
- attente 4s
- puis echec propre

### 5. Mettre un fallback de modele

Exemple :

- chatbot principal sur `gpt-5.4-mini`
- si le cout ou la charge depasse un seuil, bascule temporaire vers `gpt-5.4-nano`

Tres utile pendant les heures de pointe.

## Strategie tokens : comment eviter les depassements et les couts inutiles

### 1. Limiter la taille des messages

Vous limitez deja le message a `2000` caracteres. C'est bien.

Il faut aussi :

- limiter la longueur des reponses,
- limiter l'historique injecte au modele,
- resumer l'historique long au lieu d'envoyer toute la conversation.

### 2. Fixer un plafond de sortie raisonnable

Pour un chatbot pedagogique, il ne faut pas laisser sortir des reponses trop longues en permanence.

Recommandation initiale :

- reponse normale : `300` a `700` tokens max
- mode detaille : `900` a `1200` tokens max seulement si l'eleve le demande

### 3. Resumer l'historique

Au lieu d'envoyer les 10 derniers tours bruts a chaque fois :

- gardez les 3 a 5 derniers echanges,
- ajoutez un resume de la conversation precedente,
- ajoutez seulement le contexte pedagogique utile.

### 4. Mettre le prompt statique au debut

OpenAI indique que le **Prompt Caching** fonctionne automatiquement pour les prompts de `1024` tokens ou plus, et qu'il peut reduire la latence jusqu'a `80%` et le cout d'entree jusqu'a `90%`.

Pour en profiter :

- mettez les consignes systeme fixes au debut,
- mettez les parties variables a la fin,
- evitez de changer inutilement le prompt systeme d'une requete a l'autre.

### 5. Ne pas envoyer tout le PDF

Pour des gros documents, il ne faut pas injecter tout le contenu a chaque message.

Il faut plutot :

- extraire le texte du PDF,
- le decouper en morceaux,
- stocker ces morceaux,
- recuperer seulement les passages pertinents pour la question.

Autrement dit : faire du **RAG**.

## Architecture conseillee pour votre plateforme

### Niveau minimum viable solide

- PHP web app
- appel IA cote serveur
- base MySQL
- logs applicatifs
- rate limiting par utilisateur et IP
- retries avec backoff
- monitoring cout + erreurs

### Niveau recommande pour vrai trafic

- PHP-FPM correctement configure
- Nginx ou Apache optimise
- Redis pour rate limiting et queue
- worker asynchrone
- cache applicatif
- supervision des erreurs
- dashboard de couts et d'usage

## Ce que vous devez surveiller en permanence

### Metriques techniques

- nombre de requetes par minute
- nombre de generations simultanees
- temps moyen de reponse
- taux d'erreurs `429`, `5xx`, timeout
- nombre de retries
- taille moyenne input/output tokens

### Metriques budget

- cout journalier
- cout par eleve
- cout par document
- cout par matiere
- pics horaires

OpenAI recommande aussi de definir :

- des **usage limits**,
- des **notification thresholds**,
- et de suivre le dashboard d'usage.

## Ce que dit la doc OpenAI sur les limites

La documentation officielle indique que :

- les rate limits sont definies au niveau **organisation** et **projet**, pas au niveau utilisateur,
- les limites varient selon le modele,
- les **usage tiers** augmentent automatiquement selon l'historique de paiement,
- il existe aussi des plafonds mensuels de depense.

Consequence importante :

Meme si votre application limite bien chaque eleve, vous pouvez quand meme saturer votre quota global si beaucoup d'eleves utilisent le chatbot en meme temps.

## Est-ce qu'il faut payer ?

### Oui, pour une vraie production

Si vous voulez un chatbot fiable pour une plateforme d'eleves en production, il faut prevoir :

- une API payante,
- un budget mensuel,
- des alertes de depense,
- et des limites de securite.

### Ce que je recommande pour commencer

1. Demarrer avec un modele de type `gpt-5.4-mini`
2. Fixer un plafond mensuel
3. Fixer un plafond journalier interne
4. Fixer un plafond par eleve
5. Surveiller pendant une semaine
6. Ajuster ensuite

## Important pour une plateforme educative avec mineurs

OpenAI publie une guidance specifique pour les services destines aux mineurs.

Pour votre cas, il faut mettre en place :

- une information claire indiquant que l'outil est une IA,
- des filtres de contenu adaptes a l'age,
- une surveillance des interactions a risque,
- des mecanismes de signalement ou d'escalade,
- des regles claires contre la triche scolaire et les contenus inappropries.

## Plan d'action concret pour votre projet

### Priorite 1 - aujourd'hui

1. Regenerer la cle API actuellement presente dans `includes/config.php`
2. Deplacer la cle dans une variable d'environnement ou un fichier non versionne
3. Ajouter un vrai rate limit par utilisateur + IP + global
4. Ajouter des logs propres pour les erreurs de surcharge et timeout

### Priorite 2 - tres bientot

1. Mettre une queue Redis ou SQL
2. Mettre un retry avec backoff exponentiel
3. Limiter plus finement les tokens de sortie
4. Ajouter un tableau de bord cout / erreurs / volume

### Priorite 3 - qualite pedagogique

1. Extraire le texte des PDF
2. Construire une recherche par passages pertinents
3. Injecter seulement les extraits utiles dans le prompt
4. Resumer l'historique des conversations longues

## Recommandation finale

Si votre objectif est :

- beaucoup d'eleves connectes,
- reponses rapides,
- cout maitrise,
- moins de risque de surcharge,

alors la bonne strategie est :

- **modele principal rapide et economique**
- **queue serveur**
- **rate limiting global**
- **gestion stricte des tokens**
- **monitoring budget**
- **fallback propre**

En clair : le secret n'est pas seulement "acheter un abonnement", c'est surtout **bien architecturer le chatbot**.

## Liens officiels utiles

- OpenAI Models : https://developers.openai.com/api/docs/models
- GPT-5.4 latest model guide : https://developers.openai.com/api/docs/guides/latest-model
- GPT-5.4 mini model : https://developers.openai.com/api/docs/models/gpt-5.4-mini/
- GPT-5.4 nano model : https://developers.openai.com/api/docs/models/gpt-5.4-nano
- Responses API : https://developers.openai.com/api/reference/resources/responses/methods/create
- Rate limits : https://developers.openai.com/api/docs/guides/rate-limits
- Prompt caching : https://developers.openai.com/api/docs/guides/prompt-caching
- Background mode : https://developers.openai.com/api/docs/guides/background
- Production best practices : https://developers.openai.com/api/docs/guides/production-best-practices
- Data controls : https://developers.openai.com/api/docs/guides/your-data
- Under 18 API guidance : https://developers.openai.com/api/docs/guides/safety-checks/under-18-api-guidance

## Sources verifiees

Informations verifiees a partir de la documentation officielle OpenAI consultee pendant cette preparation :

- les modeles de reference et leur positionnement,
- les prix indicatifs des modeles GPT-5.4, GPT-5.4 mini et GPT-5.4 nano,
- le fonctionnement des usage tiers,
- le fait que les rate limits soient au niveau organisation/projet,
- le prompt caching automatique a partir de `1024` tokens,
- la possibilite d'utiliser `background=true` pour les traitements longs,
- les recommandations de securite et de protection des mineurs.
