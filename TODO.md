# Eveil — reste à faire

> **La liste de travail.** C'est ici qu'on regarde ce qui reste, et ici qu'on coche — dans le même
> commit que le code. `saas-outreach-tool-user-stories.md` garde le détail derrière chaque ligne :
> le marqueur, le rollup par epic, et le paragraphe qui dit comment c'est construit et ce qui a été
> volontairement laissé de côté. Les deux restent en phase.
>
> `[x]` seulement quand c'est fait et testé. Backend fait sans écran → `[ ]` avec un `🟡` et une
> ligne qui nomme ce qui manque : à moitié cochée, une liste commence à mentir.
>
> Ordre = ordre d'exécution. Rien de la section v1 ne démarre avant que v0 sorte.

---

## v0 — le slice qui prouve le produit

**Critère de sortie** : un utilisateur donne une URL et obtient une campagne qui tourne, sans jamais
fournir de liste de leads. Tant que ce n'est pas vrai, Eveil est un crawler avec un spec.

### Epic 6 — Séquences & personnalisation ✅

- [x] **6.1** L'IA génère une séquence complète depuis le contexte projet — et depuis le profil
      cible, donc une séquence partenaire diffère (ce qui manquait à 5.1 bis)
- [x] **6.2** Accroche personnalisée par lead, prévisualisée sur trois vrais leads
- [x] **6.3** Composer, éditer et réordonner les étapes à la main

### Epic 7 — Envoi `sortant fait, entrant absent`

- [x] **7.1** Connecter un ou plusieurs comptes email SMTP/IMAP (ADR-005, ADR-027) — écran
      `/app/settings/mailboxes`, scope organization, projets autorisés cochés sur le pivot, test de
      connexion SMTP **et** IMAP qui nomme la cause (app passwords Google, SMTP AUTH M365, port
      bloqué, TLS inversé), six fournisseurs préremplis avec leur note. Les pages de documentation
      par fournisseur sont **abandonnées** : la note dans le formulaire dit la même chose là où on en
      a besoin, et six pages à maintenir se périment plus vite que les fournisseurs ne changent
- [x] **7.2** Limite d'envoi quotidienne par compte — `eveil:send-due` toutes les 5 min, au plus un
      mail par boîte et par tick, fenêtre horaire et délai minimum entre deux envois, quota compté
      sur l'adresse tous projets confondus
- [ ] **7.6** Conformité de tout envoi — opt-out « STOP », suppression list 3 couches (ADR-013,
      ADR-029) — 🟡 texte brut sans lien ni en-tête révélateur, `Message-ID` sur le domaine de
      l'expéditeur, trois couches relues **à chaque envoi**, bounce dur 5xx suppressif, échec d'auth
      qui met la boîte en erreur, coupe-circuit au-delà de 5 % de bounces. **Reste** l'entrant, qui
      demande la lecture IMAP de l'Epic 8 : la réponse reçue est **donnée à un agent** qui décide avec
      ses outils (suppression, pas intéressé, à reprendre par un humain, relance à N mois, mauvais
      interlocuteur, ignorer un auto-reply) — pas un `str_contains('STOP')`, qui rate « merci de ne
      plus m'écrire » et tout ce qui n'est pas en anglais. Plus les DSN asynchrones. D'ici là le seul
      canal d'opt-out réel n'est branché qu'à moitié
- [x] Brancher le blocage des `invalid` à l'envoi (fin de **5.5**, l'écran est fait) — `isSendable()`
      et `Lead::contactable()` sont relus à l'inscription dans la séquence et avant chaque envoi
- [ ] Activation d'une campagne → inscription des leads : fait (`EnrolCampaign`, boîte épinglée pour
      toute la séquence), mais sans écran pour suivre où en est chaque lead — c'est **8.4**
- ~~7.5 warm-up~~ — hors scope assumé (ADR-023)

### Epic 8 — Réponses & inbox `rien de fait`

- [ ] **8.1** Pause auto de la campagne sur réponse — la séquence se met en pause **avant** que quoi
      que ce soit décide (rien ne part pendant qu'on réfléchit), puis un agent lit le mail et agit par
      **tools** : `suppress_lead`, `mark_not_interested`, `mark_needs_human`, `reschedule_followup`,
      `ask_for_right_contact`, `ignore`. L'outil choisi écrit `messages.classification`, donc la
      métrique nord (réponses positives) sort du même appel. Ajouter le cas `needs_human` à
      `ReplyClassification` (six cas aujourd'hui, aucun pour « un humain doit répondre »)
- [ ] **8.2** Inbox unifiée sur tous les comptes
- [ ] **8.3** Répondre depuis l'app
- [ ] **8.4** État de chaque lead dans le pipeline
- [ ] **8.5** Dashboard projet avec les stats clés (métrique nord : réponses positives, ADR-022)

### Trous à combler dans ce qui existe déjà

- [ ] **1.1** `docker compose up -d` + `.env.example` — **rien n'est déployable aujourd'hui**
- [ ] **1.2** Mot de passe initial par variable d'env (le reste de l'auth est fait) — arrive avec 1.1
- [x] **5.1 bis** Profils partenaires dérivés par l'agent, avec `access_angle` / `partnership_angle`,
      et la séquence écrite pour un profil partenaire diffère (Epic 6)
- [x] **5.2 bis** Sociétés sans site : `domain` nullable, qualifiées sur la ligne d'annuaire,
      l'adresse publiée devient le lead
- [x] **5.2 bis** Écran superadmin du registre d'hôtes — existait déjà (`/app/app-settings/hosts`)
- [ ] **5.8** Fiche contact centralisée

---

## v1 — l'édition cloud

### Epic 9 — Organizations & permissions — *cœur, pas cloud* (tables faites, rien au-dessus)

- [ ] **9.1** Création de compte → owner de l'organization
- [ ] **9.2** Inviter des membres avec un rôle
- [ ] **9.3** Accès projet par projet — un membre sans accès reçoit un 404, pas un 403

### Epic 10 — Facturation

- [ ] **10.1** Abonnement Stripe
- [ ] **10.2** Consommation vs plan, ventilée par projet depuis `agent_runs`
- [ ] **10.3** Core gratuit sans limite artificielle en self-hosted (ADR-025)

### Epic 4 — Agent Website (rien de fait, table `recommendations` inexistante)

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

## v2 — la moitié inbound (ADR-034)

> **Bloqué** tant que les epics 6 à 8 ne sont pas finies. Chaque agent lit le **profil cible** avant la
> knowledge base — c'est ce qui le sépare d'un générateur de contenu.

- [ ] **13.1** File unique des opportunités + documents lus affichés à côté du brouillon
- [ ] **13.2** Autonomie à trois crans, par agent et par projet
- [ ] **13.3** Agent Reddit — subreddits dérivés du profil cible, sociétés croisées renvoyées en découverte
- [ ] **13.4** Articles sur les sujets manquants, sujet ancré sur le crawl, langue de la société
- [ ] **13.5** Posts X / Bluesky — un agent, deux drivers
- [ ] **13.6** Posts LinkedIn par l'API officielle (≠ Epic 11)
- [ ] **13.7** Fonctionnalités manquantes face aux concurrents, même cycle d'état que 4.5

---

## Plus tard

- [ ] **Epic 11** LinkedIn outbound — container dédié, anti-détection (ADR-008)
- [ ] **Epic 12** API publique, serveur MCP, webhooks CRM
- [ ] Drivers providers de leads (Apollo, Hunter), vérification email tierce
- [ ] Registres officiels d'entreprises (BCE/KBO, SIRENE, Companies House) comme sources de découverte

---

## Questions ouvertes à trancher

- [ ] **Tier A** — avant la première migration
- [ ] **Tier B** — avant de dessiner les écrans v0
- [ ] **Tier C** — avant l'ouverture publique et le lancement cloud

Détail dans §9 du spec.

---

## Hors scope — ne pas cocher, ne pas construire

Warm-up de boîtes · base de contacts propriétaire · relais ESP · tracking d'ouverture comme métrique ·
CRM · gestionnaire de tâches · document de stratégie · audit SEO complet, scores Lighthouse, Design
Guide, vidéo UGC, agent qui pousse des PR (§8 + ADR-034).
