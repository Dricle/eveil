# Eveil: reste à faire

> **La liste de travail.** C'est ici qu'on regarde ce qui reste, et ici qu'on coche. Dans le même
> commit que le code. `saas-outreach-tool-user-stories.md` garde le détail derrière chaque ligne :
> le marqueur, le rollup par epic, et le paragraphe qui dit comment c'est construit et ce qui a été
> volontairement laissé de côté. Les deux restent en phase.
>
> `[x]` seulement quand c'est fait et testé. Backend fait sans écran → `[ ]` avec un `🟡` et une
> ligne qui nomme ce qui manque : à moitié cochée, une liste commence à mentir.
>
> Ordre = ordre d'exécution. Rien de la section v1 ne démarre avant que v0 sorte.

---

## v0: le slice qui prouve le produit

**Critère de sortie** : un utilisateur donne une URL et obtient une campagne qui tourne, sans jamais
fournir de liste de leads. Tant que ce n'est pas vrai, Eveil est un crawler avec un spec.

### Epic 6: Séquences & personnalisation ✅

- [x] **6.1** L'IA génère une séquence complète depuis le contexte projet. Et depuis le profil
      cible, donc une séquence partenaire diffère (ce qui manquait à 5.1 bis)
- [x] **6.2** Accroche personnalisée par lead, prévisualisée sur trois vrais leads
- [x] **6.3** Composer, éditer et réordonner les étapes à la main

### Epic 7: Envoi ✅

- [x] **7.1** Connecter un ou plusieurs comptes email SMTP/IMAP (ADR-005, ADR-027), écran
      `/app/settings/mailboxes`, scope organization, projets autorisés cochés sur le pivot, test de
      connexion SMTP **et** IMAP qui nomme la cause (app passwords Google, SMTP AUTH M365, port
      bloqué, TLS inversé), six fournisseurs préremplis avec leur note. Les pages de documentation
      par fournisseur sont **abandonnées** : la note dans le formulaire dit la même chose là où on en
      a besoin, et six pages à maintenir se périment plus vite que les fournisseurs ne changent
- [x] **7.2** Limite d'envoi quotidienne par compte, `eveilcla:send-due` toutes les 5 min, au plus un
      mail par boîte et par tick, fenêtre horaire et délai minimum entre deux envois, quota compté
      sur l'adresse tous projets confondus
- [x] **7.6** Conformité de tout envoi. Opt-out « STOP », suppression list 3 couches (ADR-013,
      ADR-029): 🟡 texte brut sans lien ni en-tête révélateur, `Message-ID` sur le domaine de
      l'expéditeur, trois couches relues **à chaque envoi**, bounce dur 5xx suppressif, échec d'auth
      qui met la boîte en erreur, coupe-circuit au-delà de 5 % de bounces. L'entrant est fait avec
      l'Epic 8 : l'agent lit la réponse et agit par tools, et un filet déterministe supprime sur une
      formulation d'opt-out sans ambiguïté même si l'agent n'a pas tourné. Les DSN asynchrones sont
      lus aussi : `Status: 5.x` supprime l'adresse et arrête le lead, `4.x` ne décide rien et attend
      un jour
- [x] Brancher le blocage des `invalid` à l'envoi (fin de **5.5**, l'écran est fait), `isSendable()`
      et `Lead::contactable()` sont relus à l'inscription dans la séquence et avant chaque envoi
- [x] Activation d'une campagne → inscription des leads (`EnrolCampaign`, boîte épinglée pour toute
      la séquence), avec le funnel de la campagne sur son écran et celui du projet sur le dashboard
- ~~7.5 warm-up~~. Hors scope assumé (ADR-023)

### Epic 8: Réponses & inbox ✅

- [x] **8.1** Pause auto de la campagne sur réponse. Attribution par `Message-ID` / `In-Reply-To`,
      la séquence se met en pause **avant** que quoi que ce soit décide, puis l'agent `reply-handler`
      agit par tools : `SuppressLead`, `MarkNotInterested`, `MarkNeedsHuman`, `RescheduleFollowUp`,
      `AskForRightContact`, `IgnoreReply`. L'outil appelé écrit `messages.classification`, donc la
      métrique nord sort du même appel. Un auto-reply reconnu aux en-têtes ne met jamais en pause et
      ne coûte pas d'appel ; reconnu par l'agent, il **relance** la séquence. `needs_human` ajouté à
      `ReplyClassification`, et il ne compte pas comme réponse positive
- [x] **8.2** Inbox unifiée sur tous les comptes: `/app/inbox`, cinquième entrée de la nav. Seules
      les vraies conversations y arrivent : un lead écrit qui n'a rien dit est une séquence en cours,
      pas une ligne d'inbox. Trié par ce qui demande une personne, filtrable par campagne
- [x] **8.3** Répondre depuis l'app: depuis la boîte que la séquence a épinglée, dans le même
      thread, sujet préfixé une seule fois. Répondre à la main **arrête** la séquence : quelqu'un à
      qui une personne écrit ne doit pas recevoir en plus la relance automatique
- [x] **8.4** État de chaque lead dans le pipeline. Funnel par statut sur le dashboard
      (`pending`, `running`, `paused`, `completed`, `stopped`, `failed`)
- [x] **8.5** Dashboard projet avec les stats clés: taux de réponse **positive** en tête (jamais le
      brut, qui compte les « non merci » et les absences du bureau comme des gains), réponses en
      attente d'une personne, leads et sociétés encore en jeu, campagnes actives, funnel, activité
      récente des agents, et tokens. Jamais d'euros
- [x] Lire les DSN asynchrones: `multipart/report` parsé depuis la partie `message/delivery-status`
      et pas depuis la prose (chaque provider la formule autrement, tous mettent `Status: 5.1.1` au
      même endroit) ; le `Message-ID` d'origine rendu dans le rapport dit **quel** envoi a échoué

### Onboarding ✅

- [x] Un nouvel utilisateur qui donne l'URL de son produit atterrit sur un **run guidé**
      (`/app/onboarding`) et non sur un dashboard vide : il regarde le site être lu en direct, valide
      ce qui a été compris. Et valider **démarre** l'étape suivante: , valide les segments, ce qui
      démarre les recherches. L'état est déduit des faits du projet (analyse, profils, runs), jamais
      d'une colonne « étape 3 » qui pourrait mentir
- [x] Deux bandeaux en haut de chaque écran, parce qu'aucun des deux ne se signale tout seul : pas de
      clé provider (superadmin uniquement. Personne d'autre ne peut la poser) et pas de boîte mail
      attachée au projet

### Trous à combler dans ce qui existe déjà

- [x] **1.1** Déployable : `deploy/` porte son `Dockerfile` (`php:8.5-fpm` + nginx + supervisord dans un
      seul conteneur, le pattern déjà éprouvé sur beryl), sa conf nginx, son supervisord et son
      `.env.example` commenté ; `compose.deploy.yaml` est à la racine (Compose prend son répertoire
      de projet là où vit le fichier, donc un compose sous `deploy/` ne lirait jamais le `.env` que
      les instructions viennent de faire écrire). Les deux clés de chiffrement sont **générées au
      premier boot** dans le volume storage et relues ensuite : personne ne copie-colle une clé, et
      surtout `CREDENTIALS_KEY` ne bouge pas d'un redémarrage à l'autre. Elle chiffre tous les mots
      de passe de boîtes, une clé régénérée les rendrait définitivement illisibles. Ce qui est posé
      dans `.env` gagne, clé par clé. Séparé du `compose.yaml` racine, qui est Sail : livrer une stack de dev comme produit
      serait le contraire du but. `docker compose -f compose.deploy.yaml up -d`
- [x] **1.2** Mot de passe initial par variable d'env: `eveil:install`, lancé par l'entrypoint à
      chaque boot, idempotent : une fois qu'un compte existe il ne fait rien, donc un redémarrage ne
      remet jamais un mot de passe à zéro. Sans `ADMIN_EMAIL`/`ADMIN_PASSWORD` c'est l'écran de setup
      qui demande: pas de mot de passe par défaut, une instance joignable avec un mot de passe connu
      est pire qu'une instance où personne ne peut encore entrer
- [x] **5.1 bis** Profils partenaires dérivés par l'agent, avec `access_angle` / `partnership_angle`,
      et la séquence écrite pour un profil partenaire diffère (Epic 6)
- [x] **5.2 bis** Sociétés sans site : `domain` nullable, qualifiées sur la ligne d'annuaire,
      l'adresse publiée devient le lead
- [x] **5.2 bis** Écran superadmin du registre d'hôtes. Existait déjà (`/app/app-settings/hosts`)
- [x] **5.8** Fiche contact centralisée: `/app/contacts/{id}` : provenance de l'adresse et son
      verdict de vérification, la société en objet référencé avec ses raisons de fit, les séquences
      où la personne est avec sa boîte épinglée et sa prochaine action, et tous les mails dans les
      deux sens. Un lead effacé rend 404 : la ligne survit pour que la découverte ne le retrouve
      jamais, mais il n'y a plus rien à montrer

---

## v1. L'édition cloud

### Epic 9: Organizations & permissions. *Cœur, pas cloud* (tables faites, rien au-dessus)

- [ ] **9.1** Création de compte → owner de l'organization
- [ ] **9.2** Inviter des membres avec un rôle
- [ ] **9.3** Accès projet par projet: un membre sans accès reçoit un 404, pas un 403

### Epic 10. Facturation

- [x] Couture de refus de dépense posée : `SpendGuardInterface` consulté par le middleware de
      métrage avant l'appel au provider, `UnmeteredSpend` en self-hosted. Reste au cloud d'y brancher
      son portefeuille
- [ ] **10.1** Abonnement Stripe
- [ ] **10.2** Consommation vs plan, ventilée par projet depuis `agent_runs`
- [ ] **10.3** Core gratuit sans limite artificielle en self-hosted (ADR-025)

### Epic 4: Agent Website (rien de fait, table `recommendations` inexistante)

- [ ] **4.1** Liste de pistes d'amélioration du site
- [ ] **4.4** Leviers d'acquisition manquants, chacun citant sa preuve (ADR-032)
- [ ] **4.5** États `proposed` → `done` / `archived`, identité par clé stable
- [ ] **4.6** Chat par projet, propositions en liste latérale
- [ ] **4.2** Relancer une analyse, diff avec la précédente
- [ ] **4.3** Historique des analyses

### Reste de v1

- [ ] **2.4** Dashboard multi-projet
- [ ] **3.3** Lier un repo GitHub pour une analyse plus poussée
- [ ] **5.7** Brancher un provider de leads tiers avec sa clé
- [ ] **5.9** Rendu JS pour les annuaires vides côté serveur (reporté)
- [ ] **6.4** A/B des variantes par étape
- [ ] **6.5** Variables et blocs conditionnels dans les templates
- [ ] **7.3** Ramp-up sur un nouveau compte
- [ ] **7.4** Rotation des boîtes sur une campagne
- [ ] OAuth Gmail / Microsoft

---

## v2. La moitié inbound (ADR-034)

> **Bloqué** tant que les epics 6 à 8 ne sont pas finies. Chaque agent lit le **profil cible** avant la
> knowledge base: c'est ce qui le sépare d'un générateur de contenu.

- [ ] **13.1** File unique des opportunités + documents lus affichés à côté du brouillon
- [ ] **13.2** Autonomie à trois crans, par agent et par projet
- [ ] **13.3** Agent Reddit: subreddits dérivés du profil cible, sociétés croisées renvoyées en découverte
- [ ] **13.4** Articles sur les sujets manquants, sujet ancré sur le crawl, langue de la société
- [ ] **13.5** Posts X / Bluesky. Un agent, deux drivers
- [ ] **13.6** Posts LinkedIn par l'API officielle (≠ Epic 11)
- [ ] **13.7** Fonctionnalités manquantes face aux concurrents, même cycle d'état que 4.5

---

## Plus tard

- [ ] **Epic 11** LinkedIn outbound: container dédié, anti-détection (ADR-008)
- [ ] **Epic 12** API publique, serveur MCP, webhooks CRM
- [ ] Drivers providers de leads (Apollo, Hunter), vérification email tierce
- [ ] Registres officiels d'entreprises (BCE/KBO, SIRENE, Companies House) comme sources de découverte

---

## Questions ouvertes à trancher

- [ ] **Tier A**. Avant la première migration
- [ ] **Tier B**: avant de dessiner les écrans v0
- [ ] **Tier C**. Avant l'ouverture publique et le lancement cloud

Détail dans §9 du spec.

---

## Hors scope: ne pas cocher, ne pas construire

Warm-up de boîtes · base de contacts propriétaire · relais ESP · tracking d'ouverture comme métrique ·
CRM · gestionnaire de tâches · document de stratégie · audit SEO complet, scores Lighthouse, Design
Guide, vidéo UGC, agent qui pousse des PR (§8 + ADR-034).
