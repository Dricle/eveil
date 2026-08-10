# User stories — outil marketing/sales IA multi-projet (self-hosted + cloud payant)

Basé sur les fonctionnalités réelles de lemlist et de son alternative open-source Linki, plus le braindump de Clément du 2026-08-09 (gestion multi-projet, auto-analyse à l'enregistrement, architecture en agents IA dédiés par projet). Scope MVP en premier (Epics 1-10), extensions cloud ensuite (Epics 11-13).

## Architecture générale

Un compte = plusieurs **projets** (un projet = un produit/site à faire connaître, ex: Dricle, Sendboo). Chaque projet a son propre espace avec plusieurs agents IA spécialisés qui tournent dessus :
- **Agent Website** — analyse le site du projet et propose des pistes d'amélioration. Il peut optionnellement analyser le repo github aussi pour potentiellement trouver des features pas assez mises en avant.
- ---> cette analyse va dresser un portrait complet du site que l'agent Sales utilisera comme base de connaissance (un commercial doit tout savoir du produit qu'il vend)
- **Agent Sales** — trouve des leads et fait tourner les campagnes d'outreach pour ce projet (c'est le cœur "lemlist-like", scope des Epics 5 à 10).

Faudrait aller plus loin  dans l'archi user / project
Idéalement : un compte user peut créer des Organizations (entité billable en mode cloud). Dans une Organization, un user peut créer des projets. Dans une Organization; un user peut inviter d'autres users, et ensuite donner accès aux projets, ou non. Dans une organization, un user peut attribuer le role "admin" à un autre user
Roles (v1) : Superadmin, Admin, Member

## Epic 1 — Setup & auth

- En tant que superadmin, je veux déployer l'app via `docker compose up -d` avec un `.env` minimal (URL, secret, mot de passe), pour être opérationnel en quelques minutes en self-hosted.
- En tant que superadmin, je veux me connecter à l'interface avec un mot de passe simple que j'aurai défini lors du setup via docker
- En tant que superadmin, je veux connecter un ou plusieurs comptes email (SMTP/IMAP) à l'app, chacun avec ses propres limites d'envoi quotidiennes (partagés entre projets ou dédiés à un projet, au choix).
- En tant que superadmin, je peux désactiver les inscriptions de mon instance self hosted via docker compose yml (env variable)
- En tant que superadmin connecté à mon instance self hosted, je veux pouvoir faire des modifications de config depuis une section settings de l'app
- En tant que superadmin dans mes settings je veux pouvoir sélectionner mon AI provider et indiquer ma clé api. Ceci sera utilisé pour toutes les organizations et tous les projets.

## EPIC 1 bis - Cloud
- En tant que non tech user, je veux pouvoir créer mon compte sur la version cloud du projet (page register classique) (et devenir admin de mon organization)

## Epic 2 — Gestion multi-projet

- En tant qu'utilisateur, je veux créer plusieurs projets distincts, chacun avec son propre nom, URL, et configuration.
- En tant qu'utilisateur, je veux basculer facilement d'un projet à l'autre depuis un sélecteur global, sans perdre le contexte de ce que je faisais.
- En tant qu'utilisateur, je veux que les leads, campagnes, comptes email connectés et résultats d'analyse soient cloisonnés par projet (pas de mélange entre deux produits différents).
- En tant qu'utilisateur, je veux une vue d'ensemble multi-projet (dashboard global) qui résume l'état de chaque projet en un coup d'œil (leads actifs, campagnes en cours, dernières suggestions des agents).
- En tant qu'utilisateur je veux pouvoir switch sur un autre projet dans mes organisations 

## Epic 3 — Onboarding projet & auto-analyse

- En tant qu'utilisateur, quand j'enregistre un nouveau projet avec juste son URL, je veux qu'un scraping automatique du site se lance immédiatement (pages, contenu, positionnement, offre).
- En tant qu'utilisateur, je veux pouvoir lier optionnellement le repo GitHub du projet pour une analyse plus poussée (stack technique, structure du code, éventuellement issues/roadmap ouvertes).
- En tant qu'utilisateur, je veux voir un résumé automatique du projet généré après l'analyse (ce que fait le produit, à qui il s'adresse, comment il se positionne) que je peux corriger/compléter manuellement.
- En tant qu'utilisateur, je veux que cette analyse initiale serve de base de contexte partagée aux deux agents (Website et Sales) plutôt que de la ressaisir pour chacun.

## Epic 4 — Agent Website

- En tant qu'utilisateur, je veux qu'après l'analyse initiale, l'agent Website me propose une liste de pistes d'amélioration concrètes du site (UX, clarté du message, SEO, vitesse, conversion).
- En tant qu'utilisateur, je veux que chaque suggestion soit priorisée (impact estimé) et explicable (pourquoi cette recommandation).
- En tant qu'utilisateur, je veux pouvoir relancer une analyse à la demande (après avoir modifié le site) pour voir si les points soulevés sont résolus.
- En tant qu'utilisateur, je veux un historique des analyses passées par projet, pour suivre l'évolution du site dans le temps.

## Epic 5 — Agent Sales : import et gestion des leads

- En tant qu'utilisateur, je veux importer une liste de leads via CSV (nom, email, entreprise, poste, etc.), avec un template téléchargeable.
- En tant qu'utilisateur, je veux que chaque ligne importée ne nécessite qu'un email et/ou une URL LinkedIn pour être valide (listes mixtes acceptées).
- En tant qu'utilisateur, je veux voir une fiche contact centralisée par lead (historique d'outreach, statut d'enrichissement, activité par campagne), scopée au projet courant.
- En tant qu'utilisateur, je veux que les entreprises soient un objet séparé et dédupliqué, lié aux contacts (pas de duplication d'infos entreprise par lead).
- En tant qu'utilisateur, je veux que l'agent Sales puisse lui-même proposer/rechercher des leads pertinents pour le projet, pas seulement en import manuel.
- En tant qu'utilisateur, l'import CSV est optionel, je m'attends à ce que la liste des leads soit automatiquement remplie par les agents IA (à définir comment, scrapping ?)

## Epic 6 — Agent Sales : enrichissement IA des leads

- En tant qu'utilisateur, je veux qu'un agent IA génère une accroche personnalisée unique par lead à partir du contexte du projet (issu de l'auto-analyse) sans recherche manuelle par contact.

## Epic 7 — Agent Sales : construction de campagnes multicanal

- En tant qu'utilisateur, je veux créer une campagne avec un enchaînement d'étapes configurables (email, délai, autre email...) dans l'ordre de mon choix.
- En tant qu'utilisateur, je veux que l'IA génère une séquence complète de cadence à partir du contexte du projet (ICP, positionnement, proposition de valeur déjà connus grâce à l'auto-analyse), en moins de 5 minutes.
- En tant qu'utilisateur, je veux assigner plusieurs variantes de template à une étape et les faire tourner en A/B automatiquement.
- En tant qu'utilisateur, je veux voir l'état de chaque lead dans le pipeline (vue funnel par étape, avec comptage par statut : en cours, terminé, échoué).
- En tant qu'utilisateur, je veux que la campagne se mette en pause automatiquement sur un lead qui répond, pour éviter de continuer à le solliciter après une réponse.
- En tant qu'utilisateur, je veux voir toutes les campagnes actives, ce que le lead a répondu, ce que l'agent IA à dit (voir la conversation), et voir où en est l'agent IA avec ce lead

## Epic 8 — Agent Sales : fiabilité de l'envoi

- En tant qu'utilisateur, je veux définir des limites d'envoi quotidiennes par compte email, avec report automatique du surplus au lendemain (pas d'arrêt de la campagne).
- En tant qu'utilisateur, je veux une montée en charge progressive (ramp-up) sur un nouveau compte email pour préserver sa réputation d'expéditeur.
- En tant qu'utilisateur, je veux connecter plusieurs comptes email pour répartir le volume d'envoi.

## Epic 9 — Agent Sales : inbox unifiée et suivi des réponses

- En tant qu'utilisateur, je veux voir toutes les réponses de mes campagnes actives dans une seule inbox, peu importe le compte email qui les a reçues, filtrable par projet.
- En tant qu'utilisateur, je veux que l'inbox affiche uniquement les contacts qui ont réellement répondu (pas de bruit).
- En tant qu'utilisateur, je veux répondre directement depuis l'app sans changer d'outil.
- En tant qu'utilisateur, je veux un tableau de bord par projet avec les stats clés (campagnes actives, contacts totaux, activité récente) en un coup d'œil.

## Epic 10 — Agent Sales : extension LinkedIn

> Automatiser LinkedIn viole ses conditions d'utilisation (risque de ban de compte). Décision de Clément le 2026-08-09 : on l'inclut quand même, même risque assumé que Linki (leur alternative open-source de référence le fait déjà).

- En tant qu'utilisateur, je veux enchaîner des actions LinkedIn (visite, connexion, message) dans une campagne, en parallèle des étapes email.
- En tant qu'utilisateur, je veux me connecter à LinkedIn directement côté serveur (pas de copier-coller de cookie), avec gestion des codes email/SMS et de l'approbation via l'app mobile.
- En tant qu'utilisateur, je veux importer une liste Sales Navigator directement depuis une URL. -> c'est une IA qui a écrit ca, je comprends pas ce que c'est ?
- En tant qu'utilisateur, je veux que les imports/actions LinkedIn respectent un rythme "humain" (délais randomisés) pour limiter le risque de détection et de ban.
- En tant qu'utilisateur, je veux que l'empreinte du navigateur utilisé pour l'automatisation reste stable dans le temps (pas de déconnexion forcée après un rebuild).

## Epic 11 — Multi-utilisateurs et permissions (version cloud)

- En tant qu'admin d'équipe, je veux inviter des membres avec des rôles différents.
- En tant qu'admin d'équipe, je veux que l'accès aux projets soit accordable par membre (pas forcément tout le monde sur tous les projets).

## Epic 12 — Facturation (version cloud payante)

- En tant qu'utilisateur cloud, je veux m'abonner à un plan payant avec carte bancaire (Stripe).
- En tant qu'utilisateur cloud, je veux voir ma consommation (leads enrichis, emails envoyés, coûts IA) par rapport à mon plan, ventilée par projet.
- En tant qu'utilisateur self-hosted, je veux que toutes les fonctionnalités core restent gratuites sans limite artificielle, la version cloud n'ajoutant que l'hébergement géré + le multi-projet illimité + le multi-user + le support.

## Epic 13 — Intégrations & API

- En tant qu'utilisateur technique, je veux une API pour créer des projets/leads/campagnes par programmation.
- En tant qu'utilisateur technique, je veux un serveur MCP pour brancher l'outil à un agent IA externe (Claude, etc.) qui pilote les projets et campagnes.
- En tant qu'utilisateur, je veux un webhook pour connecter mon CRM existant.

---

Sources de référence pour ce scope : lemlist.com (fonctionnalités IA/enrichment/multicanal), github.com/moaljumaa/linki (implémentation open-source équivalente, README consulté le 2026-08-09), braindump Clément du 2026-08-09 (multi-projet, auto-analyse, architecture agent Website/Sales).


# Stack Technique:
Monorepo
Laravel
- laravel/boost
- laravel/ai pour tout ce qui touche à l'IA (agents, mcp, etc)
- laravel/fortify pour l'auth
InertiaJS + Nuxt Ui pour la partie app
Laravel Blade + tailwind pour la partie web exposée (en version cloud) - c'est la partie SEO, le contenu que les gens vont voir en visitant la home page du site web.
Brainstorming à faire absolument sur la maniere dont sont scindé la version self hosted et la version cloud payante (2 repos séparés ? un seul repo mais avec une détection quelconque ? comment font les autres ?)
