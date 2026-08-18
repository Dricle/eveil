# Eveil — reste à faire

> Vue checklist de `saas-outreach-tool-user-stories.md`. La source de vérité reste le spec :
> une case cochée ici sans marqueur `✅` là-bas ne compte pas.
> Ordre = ordre d'exécution. Rien de la section v1 ne démarre avant que v0 sorte.

---

## v0 — le slice qui prouve le produit

**Critère de sortie** : un utilisateur donne une URL et obtient une campagne qui tourne, sans jamais
fournir de liste de leads. Tant que ce n'est pas vrai, Eveil est un crawler avec un spec.

### Epic 6 — Séquences & personnalisation `rien de fait`

- [ ] **6.1** L'IA génère une séquence complète depuis le contexte projet
- [ ] **6.2** Accroche personnalisée par lead
- [ ] **6.3** Composer les étapes à la main (porte de sortie, pas écran d'accueil)

### Epic 7 — Envoi `rien de fait`

- [ ] **7.1** Connecter un ou plusieurs comptes email SMTP/IMAP (ADR-005, ADR-027)
- [ ] **7.2** Limite d'envoi quotidienne par compte
- [ ] **7.6** Conformité de tout envoi — opt-out « STOP », suppression list 3 couches (ADR-013, ADR-029)
- [ ] Brancher le blocage des `invalid` à l'envoi (fin de **5.5**, l'écran est fait)
- ~~7.5 warm-up~~ — hors scope assumé (ADR-023)

### Epic 8 — Réponses & inbox `rien de fait`

- [ ] **8.1** Pause auto de la campagne sur réponse
- [ ] **8.2** Inbox unifiée sur tous les comptes
- [ ] **8.3** Répondre depuis l'app
- [ ] **8.4** État de chaque lead dans le pipeline
- [ ] **8.5** Dashboard projet avec les stats clés (métrique nord : réponses positives, ADR-022)

### Trous à combler dans ce qui existe déjà

- [ ] **1.1** `docker compose up -d` + `.env.example` — **rien n'est déployable aujourd'hui**
- [ ] **1.2** Mot de passe initial par variable d'env (le reste de l'auth est fait) — arrive avec 1.1
- [x] **5.1 bis** Profils partenaires dérivés par l'agent, avec `access_angle` / `partnership_angle`
      — reste la séquence d'envoi, qui arrive avec l'Epic 6
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
