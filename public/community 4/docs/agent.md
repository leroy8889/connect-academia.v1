# AGENT.md

## Philosophie du code

- **Simplicité et directivité** : moins de code est préférable. Pas d'abstractions inutiles.
- **Lisibilité et clarté** : la logique du code doit être évidente. Utilisez des noms descriptifs (auto-documentés).
- **Robustesse** : le code dupliqué est interdit. Étendre ou réutiliser systématiquement les modèles existants.
- **Robustesse** : gérez explicitement les erreurs et les cas limites. Ne masquez pas les erreurs.
- **Détection rapide des erreurs** : repérez et signalez immédiatement les erreurs. Ne jamais cacher ni retarder leur détection.

## Détection rapide et signalement explicite des erreurs

1. **Ne pas masquer les erreurs** : chaque erreur doit être gérée de manière pertinente ou propagée.
2. **Validation précoce des entrées** : vérifiez les préconditions à l'entrée de la fonction/méthode, et non en profondeur dans la logique.
3. **Pas d'erreurs silencieuses** : consigner une erreur et continuer ne suffit pas à la gérer. Si quelque chose échoue, l'appelant doit en être informé.
4. **Préserver le contexte** : lors de la relance ou de l'encapsulation d'erreurs, conservez la cause initiale visible.

## Règles principales

1. **Pureté** : Privilégiez les fonctions pures. Les effets de bord doivent être isolés et explicites.
2. **Structure** : Divisez les composants de manière logique. Évitez les fichiers monolithiques.
3. **Tests** : Ne testez pas plus d'un scénario par méthode de test, sauf pour les tests paramétrés explicitement.
4. **Nommage** : Respectez scrupuleusement les conventions de nommage du projet.
5. **Réflexions** : Réfléchissez-y à deux fois avant d'ajouter une bibliothèque. Est-il possible de l'intégrer nativement ?

## Avant toute création, vérifiez TOUJOURS ces 5 points

1. Cette logique existe-t-elle déjà dans le code source ?
2. Puis-je utiliser un modèle existant plutôt que d'en inventer un nouveau ?
3. Est-ce le bon package/répertoire pour ce code ?
4. Comment vais-je tester cette modification ?
5. **Impact** : Cela risque-t-il de perturber les fonctionnalités existantes ?

> En cas de doute, **POSEZ DES QUESTIONS** avant d'implémenter. Lorsque vous posez des questions : Fournissez le contexte, ce que vous avez déjà vérifié dans le dépôt et les pistes que vous envisagez. Ne vous contentez pas de demander « Que dois-je faire ? »