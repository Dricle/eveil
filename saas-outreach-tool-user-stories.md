# Eveil — spécification produit

> Outil marketing/sales piloté par IA, multi-projet, en édition self-hosted gratuite et cloud payante.
> Sources : lemlist.com, github.com/moaljumaa/linki.

---

## 1. Vision & positionnement

### Vision finale

> **Je donne l'URL et les infos de mon produit. L'app me trouve des clients** — directement, ou via
> ceux qui les touchent déjà.

Une entrée, une sortie. Tout le reste est de la plomberie que l'utilisateur ne devrait idéalement
jamais avoir à toucher.

C'est la boussole du projet, et elle sert de critère d'arbitrage sur chaque fonctionnalité :
**est-ce que ça réduit ce que l'utilisateur doit fournir, ou est-ce que ça augmente le nombre de
clients trouvés ?** Si ni l'un ni l'autre, ça ne rentre pas.

Deux conséquences produit à ne pas perdre de vue :

- **Le chemin principal n'est pas un constructeur de campagnes.** C'est : coller une URL, regarder
  l'app travailler, valider. Le builder d'étapes existe comme porte de sortie pour ceux qui veulent
  reprendre la main, pas comme écran d'accueil. Tous les concurrents de la catégorie font l'inverse.
- **Chaque champ obligatoire est une dette.** Un formulaire de ciblage, un import CSV, un choix de
  template : tout ce qui est demandé à l'utilisateur est un endroit où la vision n'est pas encore
  tenue. C'est acceptable comme étape intermédiaire, jamais comme état final.

### Le problème

Un solo-founder ou une petite équipe lance un produit. Le produit est bon, personne ne le connaît.
Les outils d'outreach existants (lemlist, Apollo, Instantly) supposent que l'utilisateur sait déjà
qui il cible, comment il se positionne, et qu'il arrive avec une liste de leads. C'est faux la plupart
du temps.

**Eveil part du produit, pas de la liste.** On donne une URL. L'outil lit le site, comprend ce qui est
vendu et à qui, en déduit un profil de client idéal, part chercher ces clients sur le web, et écrit
une séquence d'emails qui parle du bon produit au bon interlocuteur.

### Positionnement

**Eveil est l'alternative open source à lemlist.** C'est la catégorie : séquenceur d'outreach
multicanal avec personnalisation IA, délivrabilité et inbox unifiée. Pas un fournisseur de données,
pas un CRM, pas une plateforme de marketing automation.

Les deux formulations ne se contredisent pas, elles jouent des rôles différents. « Alternative open
source à lemlist » est **l'ancre de catégorie** : c'est ce qui rend le produit trouvable, comparable,
et immédiatement compris. « Je donne une URL, l'app me trouve des clients » est **la vision** : c'est
ce qui rend le produit différent une fois qu'on est dedans. lemlist est un outil qu'on configure ;
Eveil vise une machine qu'on lance. On entre dans le marché par l'ancre, on gagne par la vision.

Le marché de la catégorie — lemlist, Instantly, Smartlead, Saleshandy, Reply.io — est intégralement
SaaS propriétaire, facturé au siège et au volume, avec les données de prospection hébergées chez
l'éditeur. Personne ne peut auto-héberger.

Ce que l'auto-hébergement débloque, et qui n'est pas cosmétique :

1. **Boîtes d'envoi illimitées sans surcoût.** C'est exactement la variable sur laquelle Instantly et
   Smartlead facturent. En self-hosted, elle est gratuite par construction.
2. **Souveraineté des données.** Le fichier prospects ne quitte pas le serveur. Argument décisif sur
   le marché européen et pour tout ce qui touche à des secteurs régulés.
3. **Pas de facturation au siège, et le multi-utilisateur en self-hosted.** Organizations, rôles et
   invitations sont dans le cœur, pas derrière le cloud (ADR-025). Une équipe de 6 ne paie pas six
   fois — elle ne paie pas du tout si elle s'héberge.
4. **Zéro configuration de ciblage.** L'profil cible est dérivé du produit au lieu d'être rempli dans un
   formulaire. Aucun concurrent de la catégorie ne part de l'URL du produit.
5. **Contexte partagé de bout en bout.** Découverte, qualification et personnalisation puisent dans
   la même base de connaissance : Eveil sait *pourquoi* ce contact, et le dit dans le mail.

**Positionnement en une phrase** : le lemlist que tu héberges toi-même, qui part de ton produit et
non de ta liste.

### Le terrain est-il vraiment libre ?

Presque, mais pas totalement — à savoir avant de construire le discours.

**[Linki](https://github.com/moaljumaa/linki)** revendique déjà ce slot en ces termes exacts (« open
source lemlist alternative »). Open sourcé en mars 2026, LinkedIn-first (le repo se décrit comme
« Self-hosted LinkedIn outreach automation tool »), il pilote un vrai navigateur Chrome, avec un
ciblage manuel.

**Testé : le produit ne tient pas.** L'évaluation est qu'il s'agit d'un
véhicule d'acquisition pour l'hébergement managé Opsily plus que d'un produit autonome. Conclusion
pratique : le slot est revendiqué mais **pas occupé**. Il reste à prendre.

Ce que ça change concrètement : la barre à franchir n'est pas « faire mieux que Linki », elle est
« être le premier à faire ça sérieusement ». Ça ne dispense pas de citer Linki honnêtement — un projet
open source qui prétend être seul sur un créneau où un repo existe se fait corriger publiquement, et
c'est un coût de crédibilité sans contrepartie.

**Le créneau d'Eveil est donc email-first + ciblage zéro-configuration**, avec LinkedIn en canal
additionnel plus tard (ADR-008). C'est un slot défendable, mais il faut le dire précisément : « il
n'existe aucune alternative open source à lemlist » est faux et se fera corriger au premier post
Hacker News. « La seule qui soit email-first et qui déduise le ciblage du produit » tient.

Hors catégorie, et donc non concurrents : Mautic (marketing automation entrant), listmonk et Mailwizz
(newsletters), Postal (MTA). Aucun ne fait de séquencement d'outreach à froid.

### Parité lemlist — la checklist

Se dire « alternative à lemlist » engage sur un périmètre. État des lieux :

| Capacité lemlist | Statut Eveil |
|---|---|
| Séquences email multi-étapes | v0 |
| Personnalisation IA par lead | v0 — dérivée du produit, on fait mieux |
| Multi-comptes email | v0 |
| Inbox unifiée + réponses | v0 |
| Pause auto sur réponse | v0 |
| Vérification d'email | v0 |
| Rotation des boîtes sur une campagne | v1 — **manquait au doc** |
| Warm-up / lemwarm | **hors scope assumé (ADR-023)** — position documentée |
| A/B testing des variantes | v1 |
| Ramp-up nouveau compte | v1 |
| Variables conditionnelles / liquid | v1 — **manquait au doc** |
| Intégrations CRM natives + Zapier | plus tard |
| Séquences LinkedIn | plus tard (ADR-008) |
| Images et landing pages personnalisées | plus tard — la signature historique de lemlist |
| Extension Chrome | plus tard |
| Étapes appel / SMS / WhatsApp | hors scope v1 |
| Base de données B2B intégrée | **hors scope — voir §8** |

Les trois lignes en gras sont des trous ouverts par cette révision. Le warm-up en particulier :
Instantly met en avant un réseau de 4M+ comptes, lemlist vend lemwarm comme produit à part entière.
Sans réponse sur ce point, « alternative à lemlist » ne tient pas face à un utilisateur averti.

---

## 2. Personas

| Persona | Contexte | Ce qu'il veut | Ce qui le fait fuir |
|---|---|---|---|
| **Solo-founder technique** (self-hosted) | 1 à 3 produits, pas de budget, sait lancer un docker compose | Être opérationnel en 15 min, garder ses données, pas de clé API tierce à souscrire | Un onboarding qui exige 4 comptes SaaS avant le premier email |
| **Petite équipe growth** (cloud) | 2 à 8 personnes, plusieurs produits, budget modeste | Multi-user, hébergement géré, ne pas gérer d'infra | Facturation opaque, coûts IA imprévisibles |
| **Superadmin d'instance** (self-hosted) | Celui qui a lancé le docker | Couper les inscriptions, configurer le provider IA, ne rien exposer par erreur | Des réglages accessibles uniquement en éditant des fichiers |

---

## 3. Décisions actées

Décisions prises, avec le pourquoi. À ne pas rouvrir sans raison neuve.

### ADR-001 — Licence AGPL-3.0
Quiconque héberge une version modifiée doit publier son code. Bloque un concurrent qui prendrait le
code pour lancer un cloud rival, tout en restant authentiquement open source (reconnu OSI, contrairement
au fair-source). Choix de Plausible et Cal.com.
**Conséquence** : aucune dépendance sous licence incompatible.

### ADR-002 — Un seul repo, deux éditions
Le code cloud vit dans `app/Cloud/`, enregistré conditionnellement par un ServiceProvider selon
`APP_EDITION=self|cloud`. Pas de second repo, pas de build séparé.
**Pourquoi** : le modèle deux-repos de Sentry (OSS public + overlay privé) coûte une fortune en
maintenance. GitLab, Chatwoot, n8n et Plausible font tous mono-repo avec un dossier ou un module
sous licence distincte. Éprouvé.

### ADR-003 — Trois scopes de permission distincts
Ne jamais fusionner en une seule colonne `role` :

| Scope | Support | Valeurs |
|---|---|---|
| Instance | `users.is_super_admin` | booléen |
| Organization | `organization_user.role` | `owner` / `admin` / `member` |
| Projet | pivot `project_user` | simple droit d'accès, sans rôle propre |

En self-hosted mono-utilisateur, une Organization implicite est créée au setup malgré tout.
**Un seul chemin de code, jamais deux.**

### ADR-004 — Les agents sont des jobs en queue, pas des daemons
Un « agent » = un prompt + un toolset + un job. Rien de persistant, aucun processus par projet.
Chaque invocation écrit une ligne `agent_runs` (tokens, coût, durée, statut, erreur). Cette table est
simultanément le log de debug, l'historique d'analyses, et le compteur de facturation. Elle existe
dès le jour 1 — la rajouter après est un enfer.
Chaque run porte un **budget dur** (max tokens, max pages, max leads) et s'arrête dessus.

### ADR-005 — Envoi via le SMTP/IMAP de l'utilisateur
L'utilisateur connecte sa vraie boîte. C'est le modèle lemlist : les réponses arrivent naturellement
et la délivrabilité tient. **Pas de relais ESP** (Postmark, SES) — l'outreach à froid via ESP fait
bannir le compte. OAuth Gmail/Microsoft en v1, pas en v0 (validation Google longue).

### ADR-006 — Découverte sans clé API tierce
SearXNG en service docker supplémentaire (méta-moteur, gratuit, sans clé). OpenStreetMap Overpass et
l'API GitHub sont également gratuits et sans clé.
**Conséquence** : la découverte tourne en self-hosted sans souscrire à quoi que ce soit. C'est un
argument d'onboarding majeur. Risque assumé : SearXNG se fait rate-limiter ; si ça devient bloquant,
un driver Brave/Serper se branche derrière la même interface.

Complété par **ADR-033** : les annuaires et les registres officiels sont eux aussi gratuits et sans
clé, et réduisent la dépendance à SearXNG comme point d'entrée unique.

### ADR-007 — Vérification email maison
MX check puis sonde SMTP `RCPT TO` sans envoi. Détection obligatoire des domaines catch-all → ces
adresses sont marquées `risky`, pas `valid`. Gmail et Outlook bloquent les sondes → statut `unknown`,
jamais `invalid`. Un vérifieur tiers pourra se brancher en driver plus tard.

### ADR-008 — LinkedIn hors v0 et v1
Login serveur + relais 2FA + empreinte navigateur stable + proxy par compte = un navigateur headless
par utilisateur. C'est un produit à lui seul. Quand ça arrivera, ce sera dans son propre container,
optionnel.
Risque assumé par ailleurs : automatiser LinkedIn viole ses CGU (risque de ban du compte utilisateur).
Décision : on l'inclura quand même, même risque que Linki.

### ADR-009 — L'autonomie est un réglage à trois crans, par projet
> *Les trois niveaux sont un réglage, pas un choix unique figé dans le produit.*

| Cran | Comportement | Pour qui |
|---|---|---|
| **Supervisé** | Validation humaine à chaque étape : profil cible, liste de sociétés, séquence, et premier lot d'envois | Premier projet, utilisateur méfiant, secteur sensible |
| **Semi-auto** *(défaut)* | Validation une fois de le profil cible et de la séquence sur un échantillon, puis pilote automatique avec retour à l'humain sur anomalie | Le cas normal |
| **Autonome** | Envoi dès l'URL, sans point d'arrêt | Utilisateur aguerri, projet secondaire, opt-in explicite |

Réglage porté par le projet (`projects.autonomy_level`), modifiable à tout moment. **Défaut =
semi-auto** : c'est le seul cran qui tienne à la fois la vision « je donne une URL » et le risque de
griller le domaine de l'utilisateur.

Les **conditions de retour à l'humain** sont communes aux crans semi-auto et autonome, et coupent
l'envoi quel que soit le réglage :
- taux de bounce au-delà d'un seuil sur une fenêtre glissante
- plainte spam, même unique
- proportion anormale de réponses négatives
- compte email en erreur d'authentification

Le cran autonome ne désactive pas ces gardes-fous. Il ne supprime que les points de validation
*a priori*, jamais les coupe-circuits.

---

### ADR-010 — PostgreSQL partout, tests inclus
*(résout A1)*

PostgreSQL est le seul moteur supporté, en dev, en test, en CI et en prod. Pas de SQLite, même pour
la suite de tests.

**Pourquoi Postgres** : les discovery runs écrivent en parallèle depuis plusieurs workers pendant
plusieurs minutes — SQLite n'accepte qu'un écrivain à la fois, même en WAL. Le schéma est très JSON
(`knowledge_base`, `target_profiles.criteria`, `agent_runs.input/output`, `campaign_steps.config`) et JSONB est
indexable. La dédup a besoin d'index uniques partiels. La recherche dans les leads aura besoin du
full-text natif. pgvector reste disponible si la knowledge base demande des embeddings plus tard.
MySQL/MariaDB n'apportent rien ici et ont un JSON plus faible.

**Pourquoi aussi en test** : SQLite en test avec Postgres en prod diverge précisément sur ce que ce
schéma utilise le plus — comportement JSONB, sensibilité à la casse, index partiels, DDL
transactionnel. Les tests passent, la prod casse. Le coût accepté : lancer la suite exige un Postgres
disponible (container ou service CI).

**Conséquences** : `postgres` dans le docker compose et dans le workflow CI ; `.env.example` pointe
sur Postgres ; le `composer setup` doit échouer clairement si aucun Postgres n'est joignable, plutôt
que de retomber silencieusement sur SQLite.

### ADR-011 — Redis + Horizon pour la queue, le cache et les locks
*(résout A2)*

Redis rejoint le docker compose (6 services : app, worker, scheduler, Postgres, SearXNG, Redis).
Horizon pilote les workers.

**Pourquoi** — cette app est fondamentalement un moteur de jobs, et quatre besoins tombent ensemble :
- **Files aux rythmes opposés**, avec des concurrences réglées séparément :

| File | Rythme | Remarque |
|---|---|---|
| `discovery` | peut saturer les workers | borné par le budget du run |
| `crawl` | throttlé par domaine | respect de robots.txt |
| `ai` | limité par le rate-limit du provider | isolé pour ne pas bloquer le reste |
| `sending` | **lent et étalé sur la journée** | jamais en rafale |
| `imap` | polling régulier | |
| `default` | le reste | |

- **Rate limiting par domaine** pendant les crawls — compteur atomique partagé entre workers.
- **Locks distribués** — deux workers ne doivent jamais traiter le même compte email ni le même
  domaine simultanément.
- **Observabilité** — `agent_runs` dit ce qu'a fait l'IA, pas pourquoi un job a disparu. Un discovery
  run qui meurt en silence est le bug le plus probable de ce projet.

Le driver `database` sur Postgres (`FOR UPDATE SKIP LOCKED`) tiendrait la charge d'une instance solo,
mais locks et rate limiting deviennent des lignes chaudes en contention et l'équilibrage entre files
reste manuel. Le container supplémentaire est jugé acceptable : Chatwoot, Plausible et Mautic en
embarquent tous un.

**Conséquences** : ajout de `laravel/horizon` ; Redis sert aussi de driver de cache et de locks ; le
dashboard Horizon est réservé au superadmin ; le compose doit démarrer Horizon, pas un `queue:work`
nu.

### ADR-012 — Clé de chiffrement dédiée aux credentials
*(résout A3)*

Les secrets utilisateurs — mots de passe SMTP/IMAP, clé du provider IA, futurs tokens OAuth — sont
chiffrés avec une clé **distincte de l'`APP_KEY`** : `CREDENTIALS_KEY`, portée par son propre
`Encrypter` et un cast dédié.

**Pourquoi** : l'`APP_KEY` chiffre aussi cookies et sessions, et la bonne pratique ops veut qu'on la
fasse tourner après une fuite. Couplée aux credentials, la faire tourner détruirait tous les comptes
email de l'instance — donc personne ne la ferait jamais tourner. Découpler coûte une variable d'env et
une quinzaine de lignes.

Garde-fous obligatoires, indépendants de ce choix :
- **Canari chiffré en base.** Vérifié au boot : s'il ne se déchiffre pas, l'app refuse de démarrer
  avec un message explicite, plutôt qu'une `DecryptException` au fond d'un job trois jours plus tard.
- **Rotation supportée** via le mécanisme natif `APP_PREVIOUS_KEYS`, transposé à `CREDENTIALS_KEY` :
  l'ancienne clé déchiffre encore pendant le ré-encodage.
- **Commande de ré-chiffrement** parcourant les colonnes chiffrées avec les anciennes clés et
  réécrivant avec la courante.
- **Avertissement au setup et dans la doc de sauvegarde** : un dump de base sans le `.env`
  correspondant ne vaut rien. Les deux se sauvegardent ensemble.

Ce que ça ne couvre pas : en cloud, tout repose sur une clé de service unique. Le chiffrement par
organization (envelope encryption) et un KMS externe restent des questions ouvertes de niveau C, non
bloquantes pour le v0.

### ADR-013 — Suppression list à trois couches, opt-out scopé au projet
*(résout A4)*

| Couche | Contenu | Périmètre | Raison du périmètre |
|---|---|---|---|
| 1. Opt-out | Désinscriptions, réponses « stop » | **Projet** | Une organization de type agence prospecte pour plusieurs clients sans lien entre eux |
| 2. Bounces | Hard bounces | **Compte email** | Une adresse peut rebondir depuis un expéditeur et pas depuis un autre |
| 3. Toxiques | Spam traps, domaines brûlés, adresses jetables | **Instance, partagé** | Ne contient aucune adresse issue d'un opt-out client, donc ne révèle rien |

Seule la couche 3 traverse les tenants. Elle est alimentée par des listes publiques et nos propres
détections, **jamais par le comportement des prospects d'un client** — sinon tester une adresse
révélerait qui prospecte qui.

Le choix du périmètre projet pour la couche 1 est délibéré : il privilégie le
cas agence. Il ouvre un risque — un prospect désinscrit d'un produit peut être resollicité par la même
entité pour un autre — compensé par deux soupapes obligatoires :

- **Une plainte spam n'est pas un opt-out.** Le désabonnement dit « pas intéressé par ce produit », la
  plainte dit « arrête ». Toute plainte escalade donc à **l'organization entière**, quelle que soit sa
  provenance.
- **Escalade automatique au second STOP.** Si la même adresse répond STOP sur deux projets d'une même
  organization, l'opt-out passe de lui-même au niveau organization. Il n'y a plus de page de
  désinscription depuis l'ADR-029 — le prospect n'a rien à cliquer, et on cesse de le solliciter avant
  qu'il ne porte plainte.

Toute vérification avant envoi consulte les trois couches.

### ADR-014 — Données cloisonnées par projet, cache de pages partagé
*(résout A5)*

Sociétés et leads restent **cloisonnés par projet**, sans registre partagé. Un **cache de pages
brutes** au niveau instance évite les re-fetch : clé = URL normalisée, TTL, contenu public uniquement.

**Pourquoi pas de registre partagé** : ce qui coûte cher dans la découverte n'est pas le fetch HTTP,
c'est la qualification LLM. Or le score de fit et sa justification sont **spécifiques à le profil cible** — la
même société vaut 90 pour un produit et 20 pour un autre. Le partage n'économiserait donc que la
partie profil cible-indépendante (contenu de page, firmographies), au prix d'une entité supplémentaire, d'une
jointure partout, et d'un arbitrage à inventer quand deux projets divergent sur les faits d'une même
société.

**Pourquoi le cache est sûr, y compris en cloud** : il ne contient que du web public, pas de la donnée
client. Contraintes : jamais de contenu authentifié, jamais de page derrière un login, clé = URL. Le
cache bénéficie d'ailleurs autant à la relance d'un même projet qu'au partage entre projets.

Réserve mineure notée : l'existence d'une entrée en cache révèle en théorie qu'une URL a été crawlée
par quelqu'un. Jugé négligeable ; si ça devait poser problème en cloud, le cache se scope par
organization sans rien changer d'autre.

### ADR-015 — Autant de profil cible que nécessaire, en CRUD libre
*(résout A6)*

Un **profil cible** (Ideal Customer Profile) est le portrait structuré du client visé : secteurs, taille,
géographie, postes, technologies, signaux déclencheurs. C'est l'objet que l'agent déduit de la
knowledge base, et il pilote toute la recherche : l'agent choisit où chercher à partir de ces
critères, puis note chaque société selon son écart avec ce portrait. Les outils concurrents le font
remplir dans un formulaire de filtres ; ici il est déduit — c'est le « zéro configuration de ciblage »
de la vision.

**Décision** : l'agent en déduit autant qu'il l'estime nécessaire, sans nombre imposé, et l'utilisateur
peut les créer, modifier et supprimer librement. Un produit vise souvent plusieurs marchés — écraser
ça en un profil moyen ne cible personne.

Conséquences de schéma, non négociables :

- **Le score de fit ne vit pas sur la société.** Une même boîte vaut 90 pour un profil et 20 pour un
  autre (voir ADR-014). Il faut séparer :

```
companies                 faits firmographiques, dédupliqués par domaine au sein du projet
company_target_evaluations   company_id + target_profile_id → fit_score, fit_reason
```

  Sans ça, deux profils qui trouvent la même société écrasent mutuellement leur évaluation.

- **Un lead trouvé par deux profils n'est pas contacté deux fois.** Règle : un lead appartient à *au
  plus une campagne active par projet*. Le second profil qui le remonte enregistre le recoupement
  sans relancer.

- **Chaque profil actif est un discovery run de plus**, donc un budget de plus. Pas de plafond dur,
  mais l'UI annonce le coût attendu quand plusieurs profils sont actifs — personne ne doit découvrir
  la facture après avoir validé huit profils.

L'écran principal reste une ligne droite : le CRUD est disponible, jamais obligatoire pour avancer.

### ADR-016 — Aucun tracking dans les emails en v0
*(résout A7)*

Ni pixel d'ouverture, ni réécriture de liens. `messages.opened_at` sort du schéma. La métrique
suivie est la **réponse**.

**Le pixel d'ouverture est inexploitable** : Apple Mail Privacy Protection pré-charge les images à la
place du destinataire et Gmail les passe par son proxy — on compterait des ouvertures qui n'ont pas eu
lieu. Il dégrade en prime le placement en boîte de réception, et poser un traceur sans consentement
sur du cold email est difficilement défendable en Europe.

**Le clic reste fiable**, mais réécrire les liens vers un domaine autre que celui de l'expéditeur est
un marqueur de spam connu. Le faire proprement exige un domaine de tracking personnalisé par
utilisateur, donc un CNAME à configurer — ce qui percute la promesse « opérationnel en 15 minutes ».
Reporté en v1, désactivé par défaut.

### ADR-017 — Sendboo n'est pas réutilisable, et c'est structurel
*(question : faut-il reconstruire un pseudo-Sendboo ?)*

Sendboo est une plateforme d'email marketing multi-tenant bâtie
sur Spatie Mailcoach, orientée e-commerce : listes, abonnés, automations, sending domains,
Store/Product/DiscountCode, extension Shopify. Réponse : **non, et il n'y a pas de rattrapage à
faire.**

**Blocage de licence** — `spatie/laravel-mailcoach` provient de `satis.spatie.be`, c'est un package
commercial payant. Impossible dans un projet AGPL auto-hébergeable : chaque self-hoster devrait
acheter sa licence, et la redistribution est exclue.

**Les modèles d'envoi sont opposés** — c'est la vraie raison :

| | Sendboo | Eveil |
|---|---|---|
| Destinataires | abonnés opt-in | prospects qui n'ont rien demandé |
| Envoi | en masse depuis un sending domain | 1-à-1 depuis la boîte perso de l'utilisateur |
| Réputation | construire celle du domaine d'envoi | protéger celle du domaine de l'utilisateur |
| Volume | des milliers d'un coup | quelques dizaines par jour et par boîte |
| Tracking | normal et attendu | nuisible (ADR-016) |

**Ce qu'Eveil doit construire côté email est petit** : envoyer via le SMTP de l'utilisateur, lire
l'IMAP, faire tourner la machine à états d'une séquence, vérifier les trois couches de suppression
avant chaque envoi. Pas de listes, pas d'abonnés, pas de segments, pas de builder d'automations, pas
de sending domains, pas de galerie de templates, pas de webhooks ESP. Tout le poids de Sendboo est
dans ce qu'Eveil n'a pas à faire.

**Où Sendboo a sa place** : en aval, comme intégration — un lead qui répond et se convertit est poussé
vers une liste Sendboo pour le nurturing. Epic 12, jamais une dépendance.

### ADR-018 — Rétention : purge automatique, défauts CNIL, configurable
*(résout A8)*

| Donnée | Défaut | Repère |
|---|---|---|
| Lead contacté | 3 ans après le dernier contact | référence CNIL prospection commerciale |
| Lead découvert jamais contacté | 6 mois | aucune relation commerciale entamée à justifier |
| Payloads `agent_runs` (input/output) | 90 jours | contiennent noms et emails |
| Métriques `agent_runs` (tokens, coût, durée, statut) | sans limite | alimentent la facturation |
| Cache de pages crawlées | TTL court | contenu public (ADR-014) |

Valeurs modifiables dans les settings, avec un **plancher imposé** — on ne doit pas pouvoir les
régler sur l'infini.

Deux mécanismes obligatoires :

- **Effacement porté par la ligne du lead.** Une demande de suppression ne peut pas se contenter de
  supprimer la ligne : le prochain discovery run retrouverait la personne et la recontacterait. Et un
  soft delete ne suffit pas non plus — `deleted_at` masque une ligne qui contient toujours le nom,
  l'adresse et l'URL LinkedIn, c'est de la conservation avec un drapeau dessus. La ligne reste donc,
  **vidée** : nom, prénom, titre, email, LinkedIn et `source_url` partent, ainsi que le sujet et le
  corps des messages déjà envoyés, qui citent l'adresse. Survivent `email_hash` — un condensé à sens
  unique, consulté à la découverte comme à l'envoi — et `erased_at`. On ne garde pas la personne, on
  garde le fait qu'il ne faut plus jamais la retrouver. Pas de table `erasures` séparée : la portée est
  le projet, exactement comme la ligne, car deux projets peuvent trouver la même personne et un seul
  s'être vu demander de l'oublier. Un effacement à l'échelle de l'organization est la même opération
  répétée par projet. Réserve assumée : trop effacer n'est jamais une infraction, pas assez si.
- **Dissociation dans `agent_runs`.** Les payloads bruts sont purgés ou anonymisés tôt ; les métriques
  survivent sans limite. Purger les leads tout en gardant les runs indéfiniment reviendrait à laisser
  la donnée personnelle derrière soi dans le compteur de facturation.

### ADR-019 — Crédits IA en cloud, clé du user en self-hosted
*(résout B1)*

**Self-hosted** : le superadmin met sa propre clé API. **Aucun suivi de crédits, aucune facturation,
aucun code de comptage.** `agent_runs` reste (debug et historique), pas le grand livre.

**Cloud** : l'utilisateur achète des crédits. Chaque action consomme un nombre de crédits défini par
une table en base, ajustable par les superadmins de l'instance cloud sans redéploiement. L'utilisateur
ne voit qu'une consommation de crédits — jamais des tokens, jamais un nom de modèle.

Dans les deux éditions, le provider IA est configuré dans l'app et **interchangeable** (provider et
modèle). Les crédits découplent le prix affiché du coût réel : changer de LLM change la marge, pas le
tarif. C'est ce qui rend C3 non bloquant.

**Grille de base** — ancrage : 1000 crédits ≈ 1 $ de coût IA interne (tiering Claude mesuré :
Opus 5 pour la planification, Haiku 4.5 pour extraction et qualification).

| Action | Unité facturée | Crédits | Coût réel |
|---|---|---|---|
| `project.analyze` | par analyse de site | **200** | **0,19 $ — mesuré** |
| `targets.derive` | par dérivation (tous profils) | **150** | **0,14 $ — mesuré** |
| `discovery.plan` | par run | 500 | 0,53 $ |
| `company.qualify` | par société évaluée | **3** | **0,0025 $ — mesuré** |
| `contact.extract` | par société retenue | 8 | 0,008 $ |
| `sequence.generate` | par campagne | 100 | 0,10 $ |
| `lead.personalize` | par lead | 3 | 0,003 $ |
| `reply.classify` | par réponse | 1 | 0,001 $ |
| `recommendations.generate` | par analyse | 120 | 0,12 $ — estimé |
| `chat.message` | par message | 15 | 0,015 $ — estimé |
| Vérification email, envoi SMTP, lecture IMAP | — | **0** | 0 $ |

Campagne type de 100 leads ≈ **3 500 crédits**. Les actions non-IA à zéro crédit sont un argument
commercial : la vérification d'email est facturée chez les concurrents.

**Mesures réelles** :

| Action | Estimé | Mesuré | Détail |
|---|---|---|---|
| `project.analyze` | 0,15 $ | **0,192 $** | 11 pages, 24 051 in / 2 877 out, 45 s |
| `targets.derive` | 0,06 $ | **0,143 $** | 4 profils, 4 456 in / 4 833 out, 69 s |
| `company.qualify` | 0,0035 $ | **0,0025 $** | moyenne sur 14 qualifications réelles |

**Le biais n'est pas uniforme, et sa cause est la sortie.** Sur Opus 5 la sortie coûte 25 $/MTok
contre 5 $ en entrée : les actions **génératives** confiées au planner produisent plus de tokens
qu'elles n'en consomment (4 833 en sortie pour 4 456 en entrée sur la dérivation de profil cible) et coûtent
donc **plus** que prévu. À l'inverse, les actions **d'extraction** confiées à Haiku rendent une sortie
structurée courte et coûtent **moins** que prévu.

La règle à appliquer aux lignes encore estimées : dimensionner la **sortie** d'abord, et se demander
si l'action génère ou si elle extrait. `recommendations.generate` et `chat.message` sont génératives
et tournent sur le planner — donc probablement sous-estimées, comme les deux premières lignes l'ont
été.

Grille corrigée : `project.analyze` 150 → 200, `targets.derive` 60 → 150, `company.qualify` 4 → 3. C'est
exactement le mécanisme prévu par cet ADR : un chiffre faux se corrige en base, sans redéploiement,
sans que le tarif client bouge.

**Facturation à l'unité de travail, jamais au « run ».** Un run qui évalue 400 sociétés ne coûte pas
ce qu'en coûte un qui en évalue 40 ; un forfait par run perd de l'argent sur les gros et vole les
petits.

Règles d'implémentation :

- **Réserver avant, régulariser après.** Un run réserve son plafond, consomme, puis rend le solde. Il
  ne peut pas débiter en fin de course — il se ferait couper à sec au milieu. **En cloud, le budget
  dur du run (ADR-004) *est* la réservation de crédits** : un seul mécanisme, pas deux.
- **Table de prix versionnée, jamais éditée en place** (`effective_from` + lignes historiques). Sans
  ça, un ajustement reprice rétroactivement le passé et la facturation devient non reproductible.
- **Chaque transaction fige les crédits facturés au moment du débit**, indépendamment de la grille
  courante.
- **Un run avorté par une erreur de notre côté n'est pas facturé** ; un run interrompu par l'utilisateur
  facture le travail réellement produit.

Ce que ça change pour B1 : **la mesure n'est pas bloquante.** On livre sur les estimations,
`agent_runs` donne le coût réel, la grille s'ajuste en base. Seul le *ratio entre actions* doit être à
peu près juste au départ, sinon une action se vend à perte sans que personne ne le voie.

### ADR-020 — Découverte insuffisante : diagnostic, puis élargissement borné
*(résout B2)*

« Rien trouvé » recouvre quatre pannes distinctes. **Diagnostiquer avant d'élargir n'est pas
optionnel** :

| Panne | Symptôme | Réponse |
|---|---|---|
| profil cible trop étroit | 3 sociétés au lieu de 100 | Élargir un critère |
| Mauvaise source | 0 résultat mais le marché existe | Changer d'outil, pas de critère |
| Fit systématiquement bas | 300 trouvées, aucune au-dessus de 40 | **L'profil cible est faux — ne jamais élargir**, remonter à l'utilisateur |
| Pas d'emails | Sociétés qualifiées, contacts introuvables | Problème d'extraction, pas de ciblage |

Élargir dans le troisième cas est le pire scénario possible : l'agent produit 100 leads hors cible,
l'utilisateur les contacte, son domaine encaisse les plaintes.

**L'épuisement du marché est un résultat, pas un échec.** Un profil cible « agences web à Namur, 5 à 20
personnes » a une taille finie. Annoncer « ton marché fait 40 sociétés, les voici » est plus utile que
racler du bruit pour remplir un quota — et aucun concurrent ne le dit, ils vendent au volume.

**Élargissement indexé sur le cran d'autonomie** (ADR-009) :

| Cran | Comportement |
|---|---|
| Supervisé | L'agent propose l'élargissement et attend la validation |
| Semi-auto | Élargit seul, rapporte ce qu'il a relâché |
| Autonome | Élargit seul, rapporte |

Bornes communes aux trois : **un axe à la fois, deux crans maximum**, dans cet ordre —
**géographie → taille → secteurs adjacents → intitulés de poste**. Jamais deux axes simultanément,
sinon on ne sait pas ce qui a fonctionné. Chaque relâchement est journalisé et affiché.

Les tentatives d'élargissement **se comptent dans le budget du run initial** et n'en ouvrent jamais un
nouveau : sans ça une boucle d'élargissement brûle des crédits sans rien produire.

### ADR-021 — Langue détectée par société, contenu généré dedans
*(résout B3)*

Trois choses distinctes, à ne pas confondre :

| Surface | v0 | Pourquoi |
|---|---|---|
| UI de l'app | Anglais seul | Vraie i18n, coût réel, zéro impact sur la qualité des leads |
| Requêtes de recherche | **Dans la langue du marché** | `agences web bruxelles` et `web agencies brussels` ne renvoient pas les mêmes entreprises — c'est de la couverture, pas du cosmétique |
| Emails sortants | **Dans la langue du prospect** | Écrire en anglais à une PME namuroise tue le taux de réponse |

**Détection par société**, pas par projet : `companies.language`, renseignée au crawl de
qualification — page déjà récupérée, donc gratuit. Cascade : attribut `lang` de la page → TLD et
géographie → défaut du projet.

**La Belgique est le cas limite qui casse les modèles simplistes** — FR, NL et EN dans le même pays,
parfois dans la même ville. Un réglage « langue du projet » ne suffit pas ; il faut la langue par
société. C'est une colonne, pas une architecture.

Le contenu généré suit sans surcoût : la personnalisation est déjà un appel LLM par lead, écrire dans
une autre langue n'est qu'une instruction de plus. **Aucun crédit supplémentaire, aucun template par
langue.**

**Template écrit à la main + lead d'une autre langue** → le template est traduit par l'IA au moment de
l'envoi, variables préservées. Le résultat est mis en cache par couple (template, langue), donc le
coût est marginal à l'échelle. La version traduite est visible en prévisualisation — l'utilisateur ne
découvre jamais après coup ce qui est parti en son nom.

### ADR-022 — Métrique nord : réponses positives, plus le gain marqué à la main
*(résout B4)*

L'app ne voit que des réponses, jamais un contrat signé. Le taux de réponse **brut** est un mauvais
indicateur : il compte les « non merci » et les absences du bureau à égalité avec les vrais intérêts.

**Métrique principale : le taux de réponse positive**, issu de la classification IA
(`reply.classify`, 1 crédit).

**La classification ne sert pas qu'à compter, elle route** — et c'est le vrai gain :

| Catégorie | Action automatique |
|---|---|
| Intéressé | Campagne en pause, remonte en haut de l'inbox |
| Pas maintenant | Relance replanifiée à N mois |
| Mauvais interlocuteur | L'agent demande le bon contact |
| Pas intéressé | Sortie propre de la campagne |
| Désinscription | Suppression list, immédiat |
| Auto-reply | **Ignoré** — ne met pas la campagne en pause |

Sans cette classification, la pause automatique sur réponse (story 8.1) ne peut pas fonctionner
correctement. Elle n'est donc pas un ajout de reporting : elle est déjà nécessaire au cœur du produit.

**Marquage manuel du gain** : un bouton « client signé » sur la fiche lead, une colonne. Ça débloque
le **coût par client**, alors que le coût par réponse positive se calcule seul (crédits dépensés ÷
réponses positives).

Afficher *« 14 € de crédits, 3 clients »* est un argument qu'aucun concurrent ne peut sortir — aucun ne
connaît son propre coût unitaire, et aucun ne le montrerait.

Ce qui reste **hors scope** (§8) : les stades de pipeline complets saisis à la main. C'est du CRM, et
ça demande une discipline de saisie que personne n'a.

### ADR-023 — Pas de warm-up : position assumée et documentée
*(résout B5)*

Eveil ne construit **aucun** mécanisme de warm-up — ni réseau mutualisé, ni échange local entre les
boîtes de l'utilisateur.

**Pourquoi** :

- **Le warm-up sert les gros volumes sur domaines neufs.** Le playbook Instantly, c'est dix domaines
  achetés, chauffés trois semaines, puis des milliers d'envois. Notre persona envoie 30 mails par jour
  depuis sa vraie boîte, vieille de plusieurs années et pleine d'historique légitime. Elle est déjà
  chaude ; il n'y a rien à chauffer.
- **Le warm-up local n'en est pas un.** Des mails qui circulent entre les deux ou trois boîtes du même
  utilisateur ne construisent aucune réputation : les filtres regardent l'engagement d'inconnus, pas
  une boucle fermée. Théâtre coûteux — scheduler, threads, fausses réponses, marquage en important.
- **Les réseaux mutualisés se font détecter.** Google et Microsoft repèrent de mieux en mieux ces
  motifs d'engagement artificiel ; l'appartenance à un réseau devient un signal négatif. Construire
  lourd sur une technique en fin de vie est un mauvais pari.
- **Ce n'est pas ce qui fait notre délivrabilité.** Elle vient du ramp-up (7.3), des plafonds
  quotidiens (7.2), de l'envoi étalé et jamais en rafale (ADR-011), de la suppression des bounces, de
  la vérification avant envoi, de la désinscription propre — et surtout de mails **personnalisés un
  par un**, qui sont le vrai discriminant anti-spam.

**Coût assumé** : une case vide dans la checklist de parité lemlist (§1), que verra un utilisateur
averti. La réponse est une **page de documentation expliquant la position**, pas un silence — plus un
point d'intégration pour brancher un service tiers si l'utilisateur y tient vraiment.

**Tier B entièrement tranché** (ADR-019 à ADR-023). Les écrans du v0 peuvent être dessinés.

### ADR-024 — Tarification cloud : crédits seuls, avec dotation d'essai
*(résout C1)*

**Modèle unique : les crédits.** Pas de formule « apporte ta clé » en cloud — celui qui veut fournir sa
propre clé installe le self-hosted, qui est gratuit et fait pour ça.

**Calibrage.** 1000 crédits = 1 $ de coût réel ; une campagne de 100 leads = 3500 crédits ≈ 3,50 €.
Marge cible **3×**, soit ~0,10 € le lead qualifié, enrichissement et séquençage compris.

| Usage mensuel | Crédits | Coût réel | Prix à 3× |
|---|---|---|---|
| 200 leads | 7 000 | 7 € | 21 € |
| 600 leads | 21 000 | 21 € | 63 € |
| 1 500 leads | 52 000 | 52 € | 157 € |

Repère : lemlist facture ~55 €/siège **et** vend l'enrichissement en plus ; Apollo tourne autour de
0,05–0,15 $ le contact exporté. À 3× on est moins cher qu'Apollo pour plus de produit.

⚠️ **Un abonnement mal calibré met dans le rouge sans prévenir** : l'IA est la totalité du coût
variable, il n'y a rien d'autre pour absorber. 29 €/mois incluant 25 000 crédits nous coûterait 25 € —
15 % de marge.

**Le risque de marge est couvert par ADR-019** : si le provider augmente ses prix, on monte le nombre
de crédits par action via une nouvelle ligne `effective_from`. La consommation des clients augmente,
leur tarif ne bouge pas.

**Dotation d'essai** : ~5 000 crédits à l'inscription, de quoi mener une campagne complète jusqu'aux
réponses. Le self-hosted étant gratuit, un essai qui ne va pas jusqu'à la première réponse ne convainc
personne.

⚠️ **La dotation est un vecteur d'abus réel, pas théorique** — le produit est une machine à extraire
des emails, et 5 000 crédits offerts valent ~100 leads qualifiés. Garde-fous obligatoires : email
vérifié, **un seul projet** en essai, **plafond de leads découverts** (pas seulement de crédits), et
**aucun export CSV** avant un premier paiement. L'utilisateur voit ses leads et peut leur écrire ; il
ne repart pas avec le fichier.

**Expiration** (défaut) : les crédits d'abonnement expirent en fin de période, les packs achetés sont
valables 12 mois. Sans expiration, on accumule une dette de crédits non consommés et un client peut
revenir trois ans plus tard avec un stock acheté au tarif de l'époque.

### ADR-025 — AGPL partout, CLA à sortie libre (modèle Postiz)
*(résout C2)*

**Un seul `LICENSE`, AGPL-3.0, tout le repo — `app/Cloud/` compris.** Pas de dossier sous licence
distincte, pas de fonctionnalité retenue côté cloud.

**Conséquence à écrire noir sur blanc** : `app/Cloud/` (ADR-002) n'est **pas** une frontière
juridique, seulement un mécanisme de chargement conditionnel. Sans cette précision, quelqu'un y
mettra du code dans six mois en le croyant protégé.

**Périmètre de `app/Cloud/` : facturation et comptage de crédits, rien d'autre.**
Stripe, `credit_prices`, `credit_wallets`, `credit_transactions`, garde-fous d'essai. Tout le reste est
dans le cœur — organizations, rôles, invitations, accès par projet compris, donc **disponibles en
self-hosted**. Le cloud n'ajoute que l'hébergement géré, la facturation et le support. C'est ce
qu'exige la promesse « le core reste gratuit sans limite artificielle » (story 10.3), et ça évite un
second chemin de code pour le multi-utilisateur.

**CLA obligatoire, à sortie bornée au logiciel libre.** Cession de *licence*, jamais de copyright — le
contributeur garde ses droits d'auteur. Le projet peut relicencier vers toute licence à la fois
FSF-libre **et** OSI-approuvée ; il ne peut **jamais** passer en propriétaire, BSL ou fair-source.

| | CLA classique | **CLA à sortie libre** | DCO seul |
|---|---|---|---|
| Changer de licence open source | Oui | **Oui** | Non |
| Passer en propriétaire ou BSL | Oui | **Non** | Non |
| Rassure contre le rug-pull | Non | **Oui** | Oui |
| Friction à la première PR | Forte | Moyenne | Nulle |

C'est le compromis qu'on garde la souplesse *dans* l'open source tout en s'interdisant
contractuellement le coup qui a coûté leur communauté à Redis, HashiCorp et MongoDB.

**Précédent suivi** : [Postiz](https://github.com/gitroomhq/postiz-app) — AGPL-3.0 pure, un seul
LICENSE, aucun dossier `ee/`, cloud faisant tourner exactement le même code, monétisation par
l'hébergement seul, `ICLA.md` + `CCLA.md` à la racine avec sortie bornée FSF/OSI. Lancé en septembre
2024 par un indie hacker solo, ~30k étoiles. C'est l'analogue le plus proche de notre situation.

**Corollaire stratégique** : le moat n'est pas le code, c'est **l'hébergement, la marque et la vitesse
d'exécution**. Les décisions futures doivent s'y aligner — retenir une fonctionnalité côté cloud ne
protégerait rien et abîmerait le message.

À faire avant publication : rédiger `ICLA.md`, `CCLA.md`, `CONTRIBUTING.md`, brancher un bot de
vérification CLA, et **faire relire l'ensemble par un juriste** — c'est la seule décision du projet
qu'on ne peut pas défaire.

### ADR-026 — Provider, modèle et timeout configurables par agent
*(résout C3)*

Le superadmin choisit **le provider, le modèle et le timeout pour chaque classe d'agent**, depuis un
écran de settings. `laravel/ai` expose déjà la liste des providers et modèles disponibles ; la liste
des agents vient du code — `AgentSettings::known()` scanne `app/Ai/Agents/`, donc ajouter un agent
ajoute une ligne à l'écran sans rien enregistrer nulle part.

**La clé de réglage est le slug de l'agent**, kebab-case du nom de classe (`EveilAgent::slug()`), et
`agent_runs.agent` stocke le même slug. Une taxonomie plus grossière — un enum `AgentType` à cinq
rôles — a été essayée puis **supprimée** : trois travaux sans rapport partageaient la ligne `planner`,
si bien que le compteur ne distinguait pas `project.analyze` de `targets.derive` alors que la grille de
crédits les facture séparément, et qu'on ne pouvait pas mettre la dérivation de profil cible sur Opus en
laissant la planification de recherche sur un modèle moins cher. Un agent = une ligne de réglage =
une ligne de coût.

**Défauts livrés** (`config/eveil.php`) — une install fraîche fonctionne sans toucher à l'écran :

| Slug | Rôle | Défaut | Timeout | Sortie structurée requise |
|---|---|---|---|---|
| `website-analyst` | Site → base de connaissance | Opus 5 | 300s | non |
| `target-profile-deriver` | Base de connaissance → profils cibles | Opus 5 | 300s | non |
| `discovery-planner` | Où chercher, quelles requêtes | Opus 5 | 300s | non |
| `company-qualifier` | Score de fit vs profil cible | Haiku 4.5 | 60s | **oui** |
| `contact-extractor` | Lecture de page → contacts structurés | Haiku 4.5 | 60s | **oui** |

Les agents à venir (writer de séquence, classifier de réponses, recommandations d'acquisition)
s'ajoutent à cette liste avec leur propre slug, pas à une catégorie existante.

**L'écran marque les agents exigeant de la sortie structurée.** Un self-hoster qui branche un petit
modèle local via Ollama sur `contact-extractor` obtiendra des extractions **cassées**, pas médiocres.
Les agents génératifs se dégradent proprement ; ces deux-là non.

**Le timeout est réglable par agent pour une raison mesurée** : la première dérivation de profil cible réelle a
pris 69 secondes et est morte sur le défaut HTTP de 60s. 300s pour les agents qui réfléchissent, 60s
pour les lectures courtes — où un timeout long ne ferait que bloquer un worker.

**Réglage de scope instance** (ADR-003), au même titre que la clé du provider : réservé au
superadmin. Aucun admin ni membre d'organization ne le voit. En cloud, le seul superadmin est
l'exploitant de l'instance — un client ne peut donc pas changer le mapping.

Note d'exploitation, pas de garde-fou produit : la grille de crédits (ADR-019) est calibrée sur ce mix
précis. Basculer `company-qualifier` sur Opus 5 multiplie par cinq le coût réel de `company.qualify`. Si
l'exploitant change le mapping en cloud, il ajuste `credit_prices` dans la foulée.

**Repli** : backoff et reprise via Horizon, **pas de bascule automatique vers un autre provider**. La
charge est asynchrone — personne n'attend devant un écran, un rate-limit se réessaie. Un failover en
cours de run produirait des scores issus de deux barèmes différents sans que ça se voie. Changer de
provider reste une opération de config assumée.

### ADR-027 — SMTP/IMAP classique uniquement, pas d'OAuth
*(résout C4)*

**Aucun OAuth**, ni en self-hosted ni en cloud. Connexion des boîtes par identifiants SMTP/IMAP
seulement. Aucune vérification Google, aucune évaluation CASA, aucun délai administratif sur le
lancement cloud.

**Correction d'un faux problème** : les IP de datacenter ne sont pas bloquées pour les connexions
IMAP/SMTP client. C4 avait été formulé sur cette hypothèse, elle est fausse.

**Le raisonnement qui tient** : l'écrasante majorité des boîtes professionnelles ne sont ni Gmail ni
Microsoft 365 — OVH, Infomaniak, Gandi, Zoho, cPanel, serveurs internes, tous en SMTP/IMAP classique
sans échéance. C'est particulièrement vrai des PME européennes, qui sont la cible.

**Risque assumé, consigné pour mémoire** :

| Fournisseur | État |
|---|---|
| Google Workspace | Basic auth supprimée depuis le 1er mai 2025. App passwords disponibles avec 2FA, mais **un admin peut les couper pour toute l'organisation** |
| Gmail grand public | App password + 2FA : fonctionnel |
| Microsoft 365 | SMTP AUTH inchangée jusqu'à fin décembre 2026, puis **désactivée par défaut** sur les tenants existants ; indisponible d'office sur les nouveaux. Retrait final annoncé pour le S2 2027 |

Un utilisateur Workspace dont l'admin a coupé les app passwords ne peut pas connecter sa boîte, et les
utilisateurs M365 basculeront progressivement à partir de janvier 2027.

**Mitigation obligatoire — le diagnostic.** Un « échec d'authentification » générique envoie
l'utilisateur chercher ailleurs. Le test de connexion doit nommer la cause exacte : *« votre
administrateur Workspace a désactivé les mots de passe d'application »*, *« SMTP AUTH est désactivé
sur votre tenant M365, voici comment le réactiver »*. Une page de documentation par fournisseur
courant, avec la procédure pas à pas. Quelques heures de travail, et ça transforme un abandon en
déblocage de trente secondes.

### ADR-028 — Export CSV en v0, archive portable avant le cloud
*(résout C5)*

**v0** : export CSV des leads et des sociétés. Un jour de travail, utile de toute façon.

**Avant l'ouverture du cloud** : archive JSON complète d'un projet, **réimportable**. Les deux éditions
faisant tourner le même code et le même schéma (ADR-025), c'est une sérialisation de sous-arbre et non
une conversion de format — la migration cloud → self-hosted est réellement tenable, contrairement à
tous les concurrents SaaS.

Deux règles absolues sur tout export, quelle que soit sa forme :

- **Jamais de secrets.** Mots de passe SMTP/IMAP, clé du provider : exclus. Un dump qui les contient
  devient un vecteur de fuite dès qu'il traîne dans un dossier de téléchargements.
- **Toujours la suppression list.** Partir sans ses opt-out revient à recontacter dans la nouvelle
  instance des gens désinscrits dans l'ancienne — manquement RGPD et générateur de plaintes, pas
  simple perte de confort.

En cloud, l'export reste **conditionné à un premier paiement** (ADR-024), sinon la dotation d'essai
devient une machine à extraire des fichiers gratuits.

### ADR-029 — Mails indiscernables d'un envoi manuel, opt-out par « STOP »
*(résout C6)*

Les mails partent depuis la boîte de l'utilisateur et doivent **ressembler exactement à ce qu'il
aurait tapé lui-même**. Tout ce qui signale un outil disparaît.

**Spécification d'envoi, pas simple intention** :

| Interdit | Autorisé |
|---|---|
| Images, pixel, CSS, HTML structuré | Texte brut ou HTML minimal |
| Bloc de pied de page, en-tête de marque | Signature, si l'utilisateur en a configuré une |
| `List-Unsubscribe`, `Precedence: bulk`, `X-Mailer` | Uniquement les en-têtes qu'un client mail humain ajoute |
| Lien de désinscription | Phrase naturelle dans le corps |
| Toute URL vers un domaine Eveil | — |

**Aucune URL Eveil ne sort jamais dans un mail.** Ni notice, ni désinscription, ni tracking
(ADR-016). Un lien vers un domaine autre que celui de l'expéditeur est un marqueur de spam **et** un
aveu d'automatisation.

**L'opt-out est une phrase**, générée par l'agent Sales dans le corps du message — de la forme
*« si ça ne vous intéresse pas, ignorez ce mail ou répondez STOP et je ne vous recontacterai plus »*.
Pas d'en-tête `List-Unsubscribe` : les destinataires ne se sont abonnés à rien, un bouton
« se désabonner » serait incohérent avec un message écrit à la main.

**Rien d'hébergé, rien de généré côté juridique.** Pas de page de notice, pas de texte art. 14, pas
d'identité légale collectée. L'obligation d'information subsiste en droit européen mais **pèse sur
l'utilisateur en tant que responsable de traitement** — Eveil est sous-traitant en cloud, hors boucle
en self-hosted. Risque assumé et documenté.

⚠️ **Conséquence technique majeure** : « répondez STOP » devient **l'unique canal de désinscription**.
La classification des réponses (ADR-022) passe donc de métrique à **mécanisme de conformité**. Rater
un « STOP », un « retirez-moi de votre liste » ou un « ne me recontactez plus », c'est continuer à
écrire à quelqu'un qui a demandé l'arrêt.

**Règle : la détection d'opt-out se trompe du bon côté.** Un faux positif coûte un lead, un faux
négatif coûte une plainte. Au moindre doute, on supprime. Détection multilingue, insensible à la
casse et à la formulation.

**DPA en cloud** : accepté électroniquement à la création de l'organization, version et date
horodatées en base. Eveil y est sous-traitant ; le document est standard et ne demande aucun échange
de PDF signés.

### ADR-030 — Le nom reste Eveil, le domaine se choisira plus tard
*(résout C8)*

Le produit s'appelle **Eveil**. Le choix du domaine est reporté ; il ne bloque ni le code ni le
schéma.

État des domaines : `eveil.com`, `.app`, `.io`, `.ai` et `.be` sont **pris**. Libres :
`eveil.dev`, `eveil.email`, `eveil.so`, `geteveil.com`, `useeveil.com`.

Limites connues et assumées :

- **L'accent.** L'orthographe française est *Éveil* ; un domaine ne peut pas la porter, donc marque et
  orthographe divergeront en permanence.
- **Mot courant et disputé.** « Éveil » sature le champ de la petite enfance en français — se classer
  sur son propre nom sera difficile.
- **Peu lisible en anglais.** La catégorie est internationale et ses acteurs ont tous des noms courts
  et prononçables (lemlist, Instantly, Smartlead, Postiz).

**À traiter avant l'ouverture publique**, pas avant : choisir le domaine, et faire une **recherche
d'antériorité de marque à l'EUIPO**. La fenêtre où renommer coûte zéro se referme au premier commit
public — après, ça coûte le repo, la doc, les étoiles GitHub et le SEO accumulé.

**Tier C entièrement tranché** (ADR-024 à ADR-030). Le registre §9 est vide : plus aucune question
ouverte bloquante.

### ADR-031 — Un profil cible peut viser un partenaire, pas seulement un client

Les profil cible portent un **type** : `customer` ou `partner`. Un profil partenaire décrit non pas qui achète,
mais **qui touche déjà l'acheteur** — qui lui rend visite, qui le facture chaque mois, qui lui est
légalement imposé.

**Pourquoi ça mérite d'exister** : la première campagne réelle a mesuré le mur. Quatre friteries
qualifiées ont donné deux leads, tous deux `info@` devinés et `risky` — les micro-commerces locaux
publient un téléphone, pas un email. Or leurs intermédiaires — un grossiste, une brasserie, une
fiduciaire spécialisée, une agence web sectorielle — sont des sociétés B2B avec un site, des personnes
nommées et des adresses publiées. **Joignabilité proche de 100 %, et un levier sans commune mesure :
une fiduciaire horeca, c'est cinquante restaurants d'un coup.**

**Ce que ça coûte en code : presque rien**, et c'est ce qui rend la piste sérieuse.

| Brique | Change ? |
|---|---|
| Knowledge base, découverte, qualification, contacts | non |
| `target_profiles.type` | ajouté |
| Dérivation | un mode partenaire, avec ses propres critères |
| Séquence d'envoi | **oui, en profondeur** |

Le dernier point n'est pas cosmétique : le mail à un grossiste n'est pas « achetez ce produit », c'est
« vos conseillers visitent 3 000 restaurants, voici le partage de revenus ». Autre proposition de
valeur, autre séquence, autre définition d'une réponse positive.

Un profil partenaire porte deux champs que le profil client n'a pas :
- **`access_angle`** — par quoi cet acteur touche le client cible, et à quelle fréquence
- **`partnership_angle`** — pourquoi l'accord est gagnant pour lui, ce qui devient l'accroche du mail

**Le signal prioritaire est l'obligation.** Caisse blanche, HACCP, AFSCA, guichet d'entreprise : les
acteurs légalement imposés ont une clientèle captive, sont peu nombreux et sont énumérables. C'est la
meilleure première cible d'un profil partenaire.

**Ce que la découverte partenaire n'a pas le droit de faire : citer une société sans l'avoir vérifiée.**
Un LLM produit volontiers des noms plausibles et faux. Comme pour les clients, une société n'entre en
base qu'après avoir été trouvée, son site récupéré et qualifiée.

**Conséquence sur la boussole** (§1) : la formule devient « je donne l'URL, l'app me trouve des clients
— directement, ou via ceux qui les touchent déjà ».

### ADR-032 — Recommandations d'acquisition : ancrées, priorisées, avec un état

L'agent Website ne produit pas que des pistes d'amélioration du site : il propose aussi des **leviers
d'acquisition absents** — programme de parrainage, contenu éditorial, présence sur un salon, offre aux
écoles du secteur. Beaucoup de fondateurs n'y pensent tout simplement pas.

**Ce n'est pas un document de stratégie.** Un rapport se lit une fois et ne fait rien. Trois règles le
séparent du playbook générique qu'un LLM sort en trente secondes :

- **Ancrage obligatoire.** Une recommandation cite la preuve tirée de la knowledge base ou du crawl.
  « Faites du contenu » ne passe pas ; « ton site n'a pas de blog alors que les trois concurrents que
  tu cites publient chaque semaine » passe. Sans preuve vérifiable, la recommandation n'est pas émise.
- **Priorisation impact / effort**, comme les pistes de l'Epic 4.
- **Un état, et il est respecté.** `proposed` → `done` ou `archived`. Une recommandation archivée ne
  revient **jamais** — même règle que l'édition manuelle de la knowledge base et que le tombstone
  d'effacement : quand l'utilisateur a tranché une fois, on ne le lui redemande pas.

**Identité stable.** Chaque recommandation porte une clé, pas un libellé. Une ré-analyse qui
reformulerait la même idée doit la reconnaître, sinon la liste se remplit de doublons à chaque passage.

**Piloté par la conversation.** Une surface de chat par projet, dans laquelle l'utilisateur dit « c'est
fait » ou « ça ne m'intéresse pas », et l'agent met l'état à jour. Personne ne gère un backlog à la
main — c'est ce qui distingue cette liste d'un outil de tâches, explicitement hors scope (§8).
`laravel/ai` fournit déjà la persistance des conversations (`RemembersConversations`), donc la
plomberie est quasi gratuite ; ce qui reste à écrire, c'est l'outil que l'agent appelle pour changer un
état.

**Affichage** : liste latérale du chat, « propositions d'amélioration », les faites et archivées
masquées par défaut.

### ADR-033 — Découverte en graphe de jobs, et les annuaires sont une source à part entière

**Le problème : la découverte par moteur de recherche est biaisée par le SEO.** Elle trouve les
sociétés qui savent se référencer. Or une partie du marché visé n'a pas de site, ou seulement une page
Facebook, ou un site que personne ne trouve avant la vingtième page de résultats. Ces sociétés-là sont
souvent les meilleures cibles — elles n'ont personne pour les démarcher.

Jeter tout résultat pointant vers un annuaire supprime la solution : `pagesdor.be/friteries/namur`
n'est pas une société, c'est **deux cents sociétés** — et c'est le seul endroit où une société sans
site publie une adresse email. La liste noire est un aiguillage, pas un filtre.

#### Cinq décisions

**1. Un résultat de recherche a deux natures, pas une.** *Entité* (une société → un candidat) ou
*index* (une page de liste → à récolter). Un résultat n'est pas filtré, il est trié.

**2. Le modèle navigue, PHP extrait.** L'IA décide **où** regarder ; le code fait le volume. Un job
`HarvestListing` récolte une page de liste en pur PHP — `sitemap.xml`, puis JSON-LD `LocalBusiness` /
`Organization`, puis sélecteurs CSS enregistrés, puis en dernier recours l'extracteur LLM. Le modèle ne
voit jamais les deux cents fiches, seulement « 60 enregistrées, 41 avec site, 12 avec email ».

> **Ne pas parier sur le JSON-LD.** « Les annuaires en émettent presque tous, le SEO est leur métier »
> est faux : personne n'en met. Sur trois annuaires belges essayés en réel, **aucun** — pagesdor.be
> renvoie une page de challenge Imperva, infobel.com répond 403 aux User-Agents inconnus et bloque
> nommément les crawlers IA dans son robots.txt, resto.be sert 737 Ko sans un seul `ld+json` parce que
> c'est une application JS.
>
> **L'extraction LLM est donc le modèle de coût par défaut, pas un dernier recours.** 0,019 $ la page,
> contre 0,0025 $ une qualification de société. Le JSON-LD reste tenté en premier parce qu'il ne coûte
> rien à essayer et qu'il est parfait quand il est là — c'est une aubaine, plus une hypothèse de
> conception. Trois conséquences qui en découlent :
>
> - **Une extraction n'est jamais repayée deux fois.** Le résultat est mis en cache sur le hash de
>   l'URL, sinon relancer une récolte pour tester un annuaire rebille chaque page.
> - **Les sélecteurs CSS deviennent le vrai gain, et non plus une optimisation spéculative.** Si le
>   modèle est le chemin normal, apprendre les sélecteurs d'un hôte **depuis la sortie du modèle**, une
>   fois, puis les rejouer gratuitement sur les pages suivantes, transforme un coût récurrent en coût
>   unique. À construire quand un annuaire aura effectivement produit plusieurs fois — pas avant.
> - Le registre `directories` mémorise **pourquoi** un hôte échoue (`blocked`, `js_only`, `jsonld`,
>   `llm`) pour ne jamais repayer un annuaire bloqué.

**3. La découverte est un graphe de jobs, pas une boucle d'outils.** Chaque nœud est un job en queue
avec son contexte minimal, sa propre ligne en base, son propre coût.

```
discovery_runs  (projet, profil cible, budget, credits_left, status)
│
├─ PlanDiscovery          1 appel IA   → produit le plan, enfile les sondes
│
├─ RunSearchQuery × N     sans IA      → résultats bruts
│   └─ TriageResults      1 appel IA, par lot d'~20 URL
│                                      → entity | directory | skip
│       ├─ HarvestListing × M  sans IA → sitemap → JSON-LD → sélecteurs → pagination
│       │   └─ ExtractEntities  IA seulement si le JSON-LD a échoué
│       └─ lignes companies
│
├─ RunOverpassProbe × N   sans IA
│
└─ ReflectAndExpand       1 appel IA, lit les agrégats → enfile la vague suivante
```

La majorité des nœuds ne touche jamais un LLM. L'IA n'intervient qu'aux points de jugement.

**4. Le registre d'annuaires s'auto-alimente.** Une table `directories` enregistre ce qui a produit :
hôte, rendement, secteurs, pays, mode d'extraction. Découvert par le tri, pas curé à la main — sinon
c'est l'œuf et la poule, on ne peut pas savoir d'avance que `pagesdor.be` compte pour les friteries.
Aux runs suivants, le planificateur interroge directement les annuaires déjà rentables, sans repasser
par un moteur de recherche. Le registre est livré amorcé avec les évidences, et **une contribution
« nouvel annuaire » est une ligne, pas une classe PHP** — un levier open source volontaire.

**5. Un seul drapeau porte le budget et l'annulation.** `discovery_runs.status` : plus de crédits →
`exhausted`, l'utilisateur annule → `cancelled`. Les jobs déjà en file le lisent à la reprise et se
suppriment. Pas de registre de jobs à tenir, pas de worker à tuer. En cloud les crédits s'épuisent et
le run s'arrête là ; l'utilisateur en rachète s'il veut continuer. En self-hosted la consommation
détaillée s'affiche et le bouton annuler écrit le même drapeau.

#### Pourquoi pas une boucle d'outils agentique

La question a été posée sérieusement et trois objections sur quatre sont tombées : `laravel/ai` cumule
déjà l'usage sur toutes les étapes (`TextGenerationLoop::buildFinalResponse()`), donc le comptage
fonctionne sans rien écrire ; `Contracts/Approvable` met en pause et reprend par outil, donc le cran
supervisé (ADR-009) se règle outil par outil ; et le cran supervisé porte de toute façon sur la
**stratégie et le contenu des emails**, pas sur chaque fetch — laisser l'agent parcourir le web est
acceptable tant qu'un plafond de crédits l'arrête.

Il reste **une** raison, et elle est dimensionnante : **le coût d'une boucle croît de façon
quadratique avec sa profondeur**, puisque chaque étape renvoie tout l'historique. Un scout à 40 étapes
ne coûte pas quatre fois un scout à 10 étapes. Le graphe de jobs garde un contexte plat.

S'y ajoutent trois bénéfices qui ne sont pas des arguments théoriques : un job se **rejoue depuis
l'interface** (la ligne en base *est* le bouton), un job vérifie en démarrant si sa page est déjà en
cache et **se supprime** si son travail n'a plus lieu d'être, et un crash à l'étape 35 ne perd pas les
34 précédentes.

**Ce que ça coûte, honnêtement** : la continuité de raisonnement. Un agent conversationnel se souvient
que « pagesdor a rendu 60 à Namur, essayons Charleroi » ; des jobs en éventail sont amnésiques. Cette
mémoire doit être **conçue** en base au lieu d'être offerte par la fenêtre de contexte — c'est le rôle
de `ReflectAndExpand`, qui lit des agrégats (rendement par annuaire, requêtes stériles, communes non
couvertes) et enfile la vague suivante. Il lit des compteurs, pas des pages : c'est ce qui le rend
abordable. Coût secondaire : plus de classes qu'une boucle d'outils.

La boucle d'outils n'est pas interdite pour autant — elle reste le bon choix **à l'intérieur d'un
annuaire** dont la pagination résiste. Locale et bornée, pas l'architecture d'ensemble.

#### Conséquence technique immédiate : HTML → markdown

`HtmlText` renvoie aujourd'hui le texte et les liens **séparément**, en jetant le libellé des ancres.
Acceptable pour la page d'une société, rédhibitoire pour une page de liste : on obtient deux cents noms
d'un côté, deux cents URL de l'autre, et rien ne les apparie. Le markdown les garde ensemble —
`[Chez Marcel](/friterie/chez-marcel-4412)`. Ce n'est pas une optimisation de tokens, c'est ce qui rend
la page exploitable. Second défaut du même ordre : `STRIP` retire `nav`, `header` et `footer`, donc
supprime les liens de pagination — « page suivante » disparaît.

#### Effets sur les décisions existantes

- **ADR-006** (découverte sans clé API) : inchangée et renforcée. Annuaires, registres et OSM sont
  gratuits et sans clé, et réduisent la dépendance à SearXNG, dont le rate-limit était le risque assumé.
- **ADR-014** (cache de pages partagé) : `crawled_pages` devient le point de contrôle d'idempotence des
  jobs, en plus d'un cache. Le même raisonnement fonde `known_hosts` — du web public, pas de la donnée
  client, donc partageable sans réserve entre organizations.
- **ADR-020** (élargissement borné) : `ReflectAndExpand` est l'endroit où ce diagnostic s'exécute. Une
  panne `wrong_source` a maintenant une réponse concrète — changer d'annuaire — au lieu d'un constat.
- **`DiscoverySourceInterface`** : l'interface survit pour les sources en un coup (Overpass, recherche web,
  registres officiels). La récolte d'annuaire ne rentre pas dedans : elle est multi-pages et budgétée,
  donc c'est un job, pas une `search()`.

#### Piste retenue, non chiffrée : les registres officiels d'entreprises

BCE/KBO (Belgique), SIRENE (France, ~30 M d'établissements), Companies House (UK) sont ouverts,
gratuits et **exhaustifs par construction** — aucun biais SEO possible. Code NACE plus commune est un
meilleur filtre de profil cible que n'importe quelle requête. Limite connue : ni email ni site, donc ils
alimentent l'enrichissement, pas l'envoi. À traiter comme des `DiscoverySourceInterface` supplémentaires quand
la récolte d'annuaires sera en place, pas avant.

**Pas de scraper Facebook.** Bloqué, contraire aux CGU, fragile. Les sociétés qui n'ont qu'une page
Facebook se rattrapent par OSM (`contact:facebook`) et par les annuaires.

---

## 4. Architecture

### Hiérarchie

```
User ──< Organization (entité facturable en cloud)
            └──< Project (un produit/site à faire connaître)
                    ├── Knowledge base (issue de l'analyse du site)
                    ├── profil cible (dérivé, éditable)
                    ├── Companies ──< Leads
                    ├── Email accounts
                    └── Campaigns ──< Steps ──< Variants
```

Tout ce qui appartient à un projet porte `project_id` et passe par un global scope.
**Une fuite de données entre projets est le pire bug possible de cette app.**

### Les deux agents

| Agent | Entrée | Sortie |
|---|---|---|
| **Website** | URL du projet, repo GitHub optionnel | Knowledge base (ce que fait le produit, pour qui, positionnement) + pistes d'amélioration du site |
| **Sales** | Knowledge base + profil cible | Sociétés qualifiées, contacts vérifiés, séquences d'outreach, réponses traitées |

L'agent Website n'est **pas** un outil d'audit SEO. Ce dont l'agent Sales a besoin, c'est la knowledge
base ; les suggestions d'amélioration tombent gratuitement du même appel LLM. Ne pas construire un
produit SEO à côté.

### Pipeline de découverte (5 étages, chacun testable seul)

```
1. profil cible derivation      knowledge base → LLM → critères structurés → édition utilisateur
2. Company discovery   l'agent planifie OÙ chercher, puis exécute ses tools
3. Qualification       fetch du site → petit modèle → score de fit + justification
4. Contact discovery   /about, /team, mentions légales → noms + postes → inférence de pattern email
5. Verification        MX, domaine jetable, catch-all, sonde SMTP
```

L'intelligence est à l'étage 2, dans la **planification de la stratégie de recherche** — pas dans la
technique de scraping.

Toolset de découverte :

| Tool | Couvre | Coût |
|---|---|---|
| `web_search` (SearXNG) | universel, requêtes générées par le LLM | gratuit, sans clé |
| `overpass_query` (OSM) | commerces locaux par catégorie + zone | gratuit, sans clé |
| `fetch_page` | annuaires, pages clients de concurrents, témoignages | gratuit |
| `github_search` | profil cible développeurs | gratuit |
| `job_board_search` | signal déclencheur : qui recrute pour le poste X a le besoin | gratuit |

Contraintes techniques :
- **Fetch HTTP simple d'abord.** Container headless ajouté seulement quand le taux d'échec sur les
  sites JS-rendered aura été mesuré. Pas avant.
- **robots.txt respecté**, rate limiting par domaine.
- **Idempotence** : dédup société par domaine, contact par email. Un run relancé ne recrée pas 400 doublons.
- **Répartition des modèles** : gros modèle pour la planification uniquement, petit modèle pour
  extraction et qualification. Facteur 10 sur la facture.

### Délivrabilité et RGPD — structurants, pas cosmétiques

À construire **avec** la fonctionnalité d'envoi, jamais après :

- **Opt-out par phrase dans le corps du message**, pas par lien ni par en-tête `List-Unsubscribe`
  (ADR-029). Le mail doit rester indiscernable d'un envoi manuel.
- **La détection d'un « STOP » est un mécanisme de conformité, pas une métrique** — c'est l'unique
  canal de désinscription. Elle se trompe du bon côté : au moindre doute, on supprime.
- **Suppression list à trois couches** (ADR-013), vérifiée avant tout envoi.
- Hard bounce → suppression automatique. Soft bounce → plafond de retry. Des bounces non traités
  tuent la réputation de l'expéditeur en quelques semaines.
- **Provenance stockée sur chaque lead** (`source`, `source_url`, `discovered_at`) — pour l'audit et
  l'affichage interne. Elle n'est **pas** injectée dans le mail : aucun texte juridique généré,
  aucune page hébergée (ADR-029). L'obligation d'information art. 14 pèse sur l'utilisateur,
  responsable de traitement.
- **Attribution des réponses** : `Message-ID` custom à l'envoi, matching `In-Reply-To`/`References` à
  la lecture IMAP. Les auto-replies (absence du bureau) sont détectés et **ne mettent pas la campagne
  en pause**.

---

## 5. Modèle de données (esquisse)

Structure indicative, pas des migrations.

```
users                  is_super_admin
organizations
organization_user      role: owner|admin|member
projects               organization_id, name, url, knowledge_base (json, éditable)
code_repositories      project_id, url, name
                       ← plusieurs par projet : un front et une API décrivent le même produit.
                         Pas `github_repositories` — le provider se lit dans l'URL, GitLab et
                         Gitea auto-hébergé comptent autant
project_user           droit d'accès

project_analyses       project_id, type: website|repo, raw, summary, status, agent_run_id,
                       code_repository_id ← null pour une analyse de site ; avec plusieurs dépôts,
                       `type = repo` ne suffit plus à dire ce qui a été analysé
target_profiles                   project_id, name, type: customer|partner, criteria (json),
                       source: agent|human, active
                       ← autant que l'agent en déduit, CRUD libre (ADR-015)
                       ← un profil partenaire porte access_angle et partnership_angle (ADR-031)

recommendations        project_id, key (identité stable), title, rationale, evidence,
                       category, impact, effort, status: proposed|done|archived,
                       decided_at, agent_run_id
                       ← archivée = ne réapparaît jamais (ADR-032)

path_hints             kind: contact|product, token, matched, hits, is_locked
                       ← les fragments d'URL qui marquent une page à lire. Table VIDE au
                         départ : aucune liste nulle part, ni en const ni en seeder. Le
                         premier site interroge le modèle, la réponse est écrite, et en
                         quelques sites les mots courants y sont — pour toute l'instance.
                         `matched`/`hits` classe, et supprime ce qui choisit des pages sans
                         jamais livrer : c'est aussi le garde-fou contre un fragment trop
                         générique, sans liste de mots interdits
known_hosts            host (unique), kind: index|entity|social|other, reason,
                       harvest_status: jsonld|llm|blocked|js_only, pages_harvested,
                       businesses_found, is_locked, last_verified_at
                       ← ce que l'app a appris du web public, partagé toute l'instance. Un projet
                         paie le modèle une fois, tous les autres en profitent. `is_locked` = un
                         humain a tranché, aucun modèle ne réécrit ; `last_verified_at` fait expirer
                         les verdicts, sinon `blocked` serait une condamnation à perpétuité
discovery_runs         project_id, target_profile_id, status, budget (json), credits_left, stats
                       ← status porte aussi exhausted|cancelled : un seul drapeau pour le
                         plafond de crédits et l'annulation (ADR-033)
discovery_tasks        discovery_run_id, kind: plan|search|triage|harvest|extract|overpass|reflect,
                       payload (json), status, attempts, agent_run_id, error, timings
                       ← un nœud du graphe de jobs : la ligne EST le bouton « rejouer » (ADR-033).
                         Table dédiée et non la table `jobs` de Laravel, qui perd la ligne au succès
directories            host, name, countries, sectors, discovery_mode: sitemap|pattern|search,
                       extraction_mode: jsonld|selectors|llm, selectors (json),
                       yield, last_harvested_at, healthy_at
                       ← auto-alimentée par ce qui a produit, amorcée avec les évidences (ADR-033)
companies              project_id, domain (unique/projet), name, website, industry, size,
                       location, language, source, source_url  ← faits seulement, pas de score
                       ← language détectée au crawl (ADR-021)
company_target_evaluations company_id, target_profile_id, fit_score, fit_reason
                       ← le fit dépend de le profil cible, jamais de la société (ADR-015)
leads                  project_id, company_id, first/last_name, title, email,
                       email_hash  ← sha256, survit à l'effacement et sert de clé de dédup
                       email_status: valid|risky|unknown|invalid,
                       email_source: scraped|inferred|provided|imported,
                       linkedin_url, source, source_url, discovered_at, status,
                       won_at   ← marquage manuel du gain (ADR-022)
                       erased_at ← la ligne vidée EST le tombstone, pas de table à part
suppressions           email|domain, reason  ← global, hors scope projet

email_accounts         organization_id, smtp/imap chiffrés,
                       daily_limit, ramp_up_started_at
                       ← l'organization POSSÈDE la boîte ; le quota est à elle, pas au projet
email_account_project  email_account_id, project_id
                       ← quels projets ont le droit d'envoyer par cette boîte. Pivot et non
                         `project_id` nullable : « null = tous les projets » offrirait
                         silencieusement l'adresse perso du fondateur au prochain projet créé
campaigns              project_id, name, status
campaign_steps         campaign_id, position, type: email|wait|linkedin, delay, config
step_variants          campaign_step_id, subject, body, weight       ← A/B
campaign_leads         campaign_id, lead_id, current_step, status, paused_at, pause_reason
messages               campaign_lead_id, direction, email_account_id, message_id,
                       in_reply_to, subject, body, sent_at, replied_at, status
                       ← pas de opened_at : aucun tracking en v0 (ADR-016)

agent_runs             project_id, agent (slug de la classe), status, input, output,
                       tokens_in, tokens_out, duration, error
                       ← des tokens, jamais des euros : aucun provider ne renvoie de prix, donc
                         tout montant serait notre propre multiplication par un tarif qui dérive.
                         Le self-hosted paie son provider et veut des tokens ; le cloud facture en
                         crédits, calibrés par l'exploitant depuis ces compteurs et sa vraie facture
                       ← les deux éditions (debug + historique)

── cloud uniquement, app/Cloud/ (ADR-019) ───────────────────────────────────
credit_prices          action, credits, effective_from, active
                       ← versionnée, jamais éditée en place
credit_wallets         organization_id, balance
credit_transactions    organization_id, type: purchase|hold|charge|release|refund|grant,
                       action, quantity, unit_credits, credits, agent_run_id,
                       balance_after   ← fige le tarif au moment du débit
```

---

## 6. Ligne de coupe

### v0 — le slice qui prouve le produit

Mono-projet. **Pas** d'organizations, pas de multi-utilisateur, pas de LinkedIn, pas de facturation.

```
URL du site → knowledge base → profil cible éditable → DiscoveryRun (search + overpass + fetch)
→ sociétés qualifiées → contacts + emails vérifiés → séquence IA 2 étapes
→ envoi SMTP plafonné → détection réponse IMAP → pause auto → inbox unifiée
```

Critère de sortie : un utilisateur donne une URL et obtient une campagne qui tourne, sans jamais
fournir de liste de leads.

### v1 — l'édition cloud

Organizations, rôles, invitations, accès par projet, multi-projet réel, dashboard global, facturation
Stripe, compteurs de consommation, OAuth Gmail/Microsoft, A/B testing, ramp-up.

### Plus tard

LinkedIn (container dédié), API publique, serveur MCP, webhooks CRM, drivers providers de leads
(Apollo, Hunter), drivers de vérification email tiers, headless browser pour sites JS.

---

## 7. User stories

Format : `En tant que <persona>, je veux <action>, pour <bénéfice>.`
Chaque story porte ses critères d'acceptation. Sans critères, impossible de dire « c'est fini ».

### Avancement

Chaque story porte un marqueur, mis à jour **dans le même commit que le code**. Une story non marquée
est une story pas faite.

| Marqueur | Sens |
|---|---|
| `✅` | Fait et couvert par des tests |
| `🟡` | Backend ou CLI fait, **pas d'interface** — le reste est indiqué sur la story |
| `⬜` | Pas commencé |

État actuel :

| Epic | Avancement |
|---|---|
| 1 — Setup & configuration | 🟡 auth complète (Fortify : login, reset, 2FA) et écran de setup faits ; réglages toujours en base et en CLI, aucun écran |
| 2 — Projets | 🟡 cloisonnement fait et testé, pas de CRUD |
| 3 — Analyse & knowledge base | 🟡 `eveil:analyze` tourne, rien n'est déclenché automatiquement |
| 4 — Agent Website | ⬜ table `recommendations` pas encore créée |
| 5 — Découverte de leads | 🟡 chaîne complète en CLI, récolte d'annuaires, registre d'hôtes appris ; manquent `target_profiles.type`, l'import CSV, le rendu JS (5.9, reporté) |
| 6 — Séquences | ⬜ |
| 7 — Envoi | ⬜ |
| 8 — Réponses & inbox | ⬜ |
| 9 — Organizations & permissions | ⬜ tables faites, rien au-dessus |
| 10 — Facturation | ⬜ |
| 11 — LinkedIn / 12 — Intégrations | ⬜ hors v0 et v1 |

**Ce qui existe vraiment** : le schéma complet, les modèles, cinq agents, le crawler, la vérification
d'emails, et quatre commandes — `eveil:analyze`, `eveil:derive-targets`, `eveil:discover-companies`,
`eveil:find-contacts` et `eveil:harvest` — plus `eveil:agent-model` et `eveil:credentials-key`. Côté interface : l'app Inertia + Nuxt UI vit
sous `/app` (le site public est en Blade, servi seulement en édition cloud) et couvre setup, login,
reset de mot de passe, 2FA et un dashboard vide. Aucun écran de réglages, aucun CRUD projet, aucun
envoi.

### Epic 1 — Setup & configuration `v0`

**1.1** ⬜ En tant que superadmin, je veux déployer l'app via `docker compose up -d` avec un `.env`
minimal, pour être opérationnel en quelques minutes.
- Le compose démarre app, queue worker, scheduler, base, SearXNG
- `.env.example` documente le minimum vital : URL, `APP_KEY`, mot de passe admin initial
- Aucune clé API tierce n'est requise pour un premier run de découverte
- Premier accès à l'URL → écran de setup, pas une erreur 500 — **fait** (voir 1.2) ; le reste de la
  story, le compose de déploiement et le `.env.example`, reste à faire

**1.2** 🟡 En tant que superadmin, je veux me connecter avec le mot de passe défini au setup.
- Le mot de passe initial vient de l'env ou du premier écran de setup
- Changeable depuis les settings
- **État** : Fortify installé ; écran de setup (`/app/setup`, superadmin + organization owner),
  login, logout, mot de passe oublié + réinitialisation par email, confirmation de mot de passe et
  2FA TOTP (activation, QR, codes de secours, désactivation sur `/app/security`) faits et testés.
  Manquent le mot de passe initial par l'env et le changement de mot de passe depuis les settings,
  qui arrivent avec 1.5

**1.3** 🟡 En tant que superadmin, je veux choisir mon provider IA et saisir ma clé depuis les settings.
- Clé chiffrée avec `CREDENTIALS_KEY` (ADR-012), jamais loggée, jamais renvoyée en clair au frontend
- Bouton « tester la connexion » avec retour immédiat
- Vaut pour toutes les organizations et tous les projets de l'instance

**1.7** ⬜ En tant que superadmin, je veux voir et corriger ce que l'app a appris sur les hôtes.
- Table `known_hosts` : hôte, verdict, motif, rendement, dernier statut de récolte
- Éditer un verdict le **verrouille** — un modèle ne le réécrira plus jamais
- Nécessaire parce qu'un mauvais verdict se met en cache avec exactement la même confiance qu'un bon,
  et à l'échelle de l'instance : un vrai prospect classé `noise` devient invisible pour tous les projets

**1.6** 🟡 En tant que superadmin, je veux choisir le provider, le modèle et le timeout **par agent IA** (ADR-026).
- Réglage de scope instance, réservé au superadmin — invisible pour les admins et membres d'organization
- Une ligne par agent, clé = le slug de la classe (`website-analyst`, `target-profile-deriver`, …) ; pas de regroupement par catégorie
- Liste des providers et modèles fournie par `laravel/ai`, liste des agents découverte dans `app/Ai/Agents/`
- Défauts livrés : une install fraîche fonctionne sans ouvrir cet écran
- Les agents exigeant une sortie structurée sont marqués comme tels
- L'écran affiche, par agent, ce qu'il a déjà coûté et sur combien d'appels
- **État** : la couche base est faite et pilotable en CLI (`eveil:agent-model`) ; l'écran reste à construire avec l'auth

**1.4** ✅ En tant que superadmin, je veux désactiver les inscriptions via variable d'env.
- `REGISTRATION_ENABLED=false` → la route register renvoie 404, pas un message d'erreur
- Défaut lié à l'édition : ouvert en cloud, fermé en self-hosted ; `REGISTRATION_ENABLED` tranche
  dans les deux sens
- Fermé = Fortify n'enregistre pas les routes : `/app/register` est un vrai 404, pas un formulaire
  qui refuse. Corollaire : aucune page n'importe `@/routes/register`, l'URL vient d'une prop
  partagée (`registerUrl`)
- L'écran d'inscription crée l'utilisateur **et** son organization (`App\Actions\CreateAccount`)

**1.5** ⬜ En tant que superadmin, je veux modifier la configuration depuis une section settings.
- Ce qui est réglable en UI est explicitement listé ; le reste reste en env

### Epic 2 — Projets `v0`

**2.1** 🟡 En tant qu'utilisateur, je veux créer un projet avec un nom et une URL.
- URL validée et joignable avant création
- L'analyse initiale se déclenche automatiquement à l'enregistrement

**2.2** ✅ En tant qu'utilisateur, je veux que tout soit cloisonné par projet.
- Leads, sociétés, campagnes, comptes email, analyses, runs d'agent portent `project_id`
- Un global scope l'applique ; un test vérifie qu'aucune requête ne fuit entre deux projets

**2.3** ⬜ `v1` En tant qu'utilisateur, je veux basculer d'un projet à l'autre depuis un sélecteur global.
- Le projet courant est en session, pas dans l'URL de chaque page
- Le changement de projet ne perd pas le contexte de travail

**2.4** ⬜ `v1` En tant qu'utilisateur, je veux un dashboard multi-projet.
- Par projet : leads actifs, campagnes en cours, dernières suggestions, consommation IA

### Epic 3 — Analyse & knowledge base `v0`

**3.1** 🟡 En tant qu'utilisateur, quand j'enregistre un projet, je veux que le site soit analysé
automatiquement.
- Crawl plafonné (nb de pages, profondeur, timeout) et affiché en cours de route
- robots.txt respecté
- Un échec partiel produit quand même une knowledge base, avec la liste de ce qui a échoué

**3.2** 🟡 En tant qu'utilisateur, je veux voir un résumé du produit que je peux corriger.
- Champs : ce que fait le produit, pour qui, positionnement, proposition de valeur, concurrents
- L'édition manuelle prime sur toute ré-analyse ultérieure, et est marquée comme telle

**3.3** ⬜ `v1` En tant qu'utilisateur, je veux lier le repo GitHub pour une analyse plus poussée.
- Repos publics d'abord ; stack technique, README, issues ouvertes
- Sert à repérer les features pas assez mises en avant sur le site

**3.4** ✅ En tant qu'utilisateur, je veux que cette analyse serve de contexte aux deux agents.
- Un seul objet knowledge base, référencé par Website et Sales, jamais dupliqué

### Epic 4 — Agent Website : pistes d'amélioration et d'acquisition `v1`

**4.1** ⬜ En tant qu'utilisateur, je veux une liste de pistes d'amélioration du site.
- Chaque suggestion : catégorie, impact estimé, justification, effort
- Tri par impact par défaut

**4.4** ⬜ En tant qu'utilisateur, je veux qu'on me signale les leviers d'acquisition qui me manquent (ADR-032). — **table `recommendations` pas encore créée**
- Parrainage, contenu éditorial, salons, écoles du secteur, programme revendeur…
- **Chaque recommandation cite sa preuve** dans la knowledge base ou le crawl ; sans preuve, pas de
  recommandation — c'est ce qui la sépare d'un conseil générique
- Priorisée par impact et effort

**4.5** ⬜ En tant qu'utilisateur, je veux marquer une recommandation faite ou sans intérêt, en le disant.
- États : `proposed` → `done` ou `archived`
- Une recommandation archivée ne réapparaît **jamais**, même après une ré-analyse
- Identité par clé stable, pas par libellé, sinon une reformulation crée un doublon
- Mise à jour depuis la conversation : l'utilisateur ne gère aucun backlog

**4.6** ⬜ En tant qu'utilisateur, je veux discuter de mon projet avec l'agent.
- Une conversation par projet, avec la knowledge base pour contexte
- Les propositions d'amélioration en liste latérale, faites et archivées masquées par défaut
- Persistance fournie par `laravel/ai` (`RemembersConversations`)

**4.2** ⬜ En tant qu'utilisateur, je veux relancer une analyse à la demande.
- L'écart avec l'analyse précédente est visible : résolu / toujours ouvert / nouveau

**4.3** ⬜ En tant qu'utilisateur, je veux l'historique des analyses par projet.

### Epic 5 — Découverte de leads `v0` — *cœur du produit*

**5.1** 🟡 En tant qu'utilisateur, je veux que le profil cible soit déduit de mon produit, sans le saisir.
- Critères structurés : secteurs, taille, géographie, intitulés de poste, technologies, signaux
- Entièrement éditable ; l'édition est conservée entre les runs

**5.1 bis** ⬜ En tant qu'utilisateur, je veux aussi des profils de **partenaires**, pas seulement de clients (ADR-031).
- `target_profiles.type` : `customer` ou `partner` — **colonne pas encore créée**
- Un profil partenaire répond à : qui visite mon client, qui le facture chaque mois, qui lui est
  **légalement imposé** — ce dernier signal en premier, sa clientèle est captive
- Il porte `access_angle` (par quoi il touche le client) et `partnership_angle` (pourquoi c'est
  gagnant pour lui, et donc l'accroche du mail)
- Découverte et qualification identiques ; **la séquence d'envoi, elle, diffère en profondeur**
- Aucune société n'est citée sans avoir été trouvée, récupérée et qualifiée

**5.2** 🟡 En tant qu'utilisateur, je veux lancer une recherche de sociétés correspondant à le profil cible.
- L'agent choisit ses sources selon le profil cible et **explique son plan avant d'exécuter**
- Progression visible en direct : sources interrogées, sociétés trouvées, budget consommé
- Budget dur (pages, tokens, leads) ; arrêt propre à la limite avec résultats partiels conservés
- Un re-run ne duplique pas : dédup par domaine

**5.2 bis** 🟡 En tant qu'utilisateur, je veux trouver aussi les sociétés que les moteurs ne classent pas (ADR-033).
- Les résultats pointant vers un **annuaire** sont récoltés, plus jetés : une page de liste vaut des
  dizaines de sociétés, et c'est le seul endroit où une société sans site publie un email
- Récolte en PHP : `sitemap.xml`, JSON-LD, sélecteurs, extracteur LLM en dernier recours
- Un annuaire qui a produit est mémorisé avec son rendement, son pays et ses secteurs, et réinterrogé
  directement au run suivant sans repasser par un moteur de recherche
- Ajouter un annuaire ne demande pas de code
- Les hôtes sont **classés par l'IA, une fois pour toutes** : impossible d'énumérer à la main tous les
  annuaires du monde, et la liste noire écrite à la main jetait justement les résultats les plus utiles
- Le verdict est **structurel, jamais une question de pertinence** : « ce site liste-t-il des
  organisations ? », pas « est-ce que ça intéresse quelqu'un ? ». Un job board est un index — une agence
  de recrutement prospecte les entreprises qui recrutent ; un journal est une `entity` — c'est une
  société, cliente potentielle de qui vend aux éditeurs. C'est cette neutralité qui rend le registre
  partageable entre tous les projets ; la pertinence se juge à la qualification, par profil
- Un annuaire est **aussi une société** : un hôte `index` est à la fois récolté ET retenu comme
  candidat. Un fondateur dont la cible est « les plateformes de lancement » veut Product Hunt et
  BetaList comme leads, pas seulement comme sources
- **État** : fait et testé — `ListingHarvester` (JSON-LD, repli LLM, pagination, budget),
  `HostRegistry` + agent `ResultTriage`, table `known_hosts` amorcée, branchement dans
  `eveil:discover-companies`. Reste : les sociétés sans site sont comptées mais pas exploitables
  (`companies.domain` est NOT NULL), et l'écran superadmin du registre

**5.2 ter** ⬜ En tant qu'utilisateur, je veux voir et reprendre la main sur ce que fait la découverte (ADR-033).
- La découverte est un graphe de jobs : chaque étape a sa ligne, son état, son coût, son erreur
- **Rejouer une étape** depuis l'interface, sans relancer le run
- **Annuler** le run : les étapes en file se suppriment à la reprise, les résultats acquis restent
- En cloud, l'épuisement des crédits arrête le run proprement — l'utilisateur en rachète s'il veut suivre
- En self-hosted, la consommation détaillée par run et par étape est affichée

**5.3** 🟡 En tant qu'utilisateur, je veux que chaque société soit scorée et justifiée.
- Score de fit + phrase de justification exploitable comme accroche
- Filtrage par score, rejet manuel possible

**5.4** 🟡 En tant qu'utilisateur, je veux des contacts avec des emails utilisables.
- Scrape des pages équipe/contact/mentions légales
- Inférence de pattern à partir d'une adresse connue sur le domaine
- Fallback générique (`contact@`) marqué comme tel
- Chaque email porte `email_source` et `email_status`

**5.5** 🟡 En tant qu'utilisateur, je veux que les emails soient vérifiés avant tout envoi.
- MX, domaine jetable, catch-all, sonde SMTP sans envoi
- Catch-all → `risky`. Gmail/Outlook bloquant la sonde → `unknown`, jamais `invalid`
- Les `invalid` ne sont jamais envoyés

**5.6** ⬜ En tant qu'utilisateur, je veux importer un CSV.
- Template téléchargeable ; email **ou** URL LinkedIn suffit pour qu'une ligne soit valide
- Rapport d'import : importés, dédupliqués, rejetés avec motif

**5.7** ⬜ `v1` En tant qu'utilisateur, je veux brancher un provider tiers avec ma clé.
- Interface `LeadSource` commune à CSV, scraping et providers
- Même porte d'entrée pour les registres officiels ouverts — BCE/KBO, SIRENE, Companies House :
  exhaustifs et sans biais SEO, mais sans email, donc ils enrichissent et n'envoient pas (ADR-033)

**5.9** ⬜ `v1` En tant qu'exploitant, je veux lire les annuaires qui ne rendent rien côté serveur.
*(reporté volontairement — déclencheur écrit, pas encore atteint)*
- **Cas mesurés à ce jour : zéro.** `resto.be` n'est pas JS-only — le serveur envoie 737 Ko et l'extracteur y lit 23 sociétés ;
  `pagesdor.be` est `blocked`, pas non-rendu
- `known_hosts.harvest_status` distingue les quatre issues : `blocked` (rien récupéré),
  `js_only` (récupéré mais moins de 500 caractères — une coquille), `no_listing` (lu correctement,
  rien dessus), `jsonld`/`llm` (a marché). Seul `js_only` serait réglé par un navigateur
- **Déclencheur : 10 hôtes ou plus en `js_only`.** En dessous, un gigaoctet de Chromium n'achète rien
- **Un navigateur ne règle PAS `blocked`** : Imperva et Cloudflare détectent aussi un headless. Y aller
  demanderait des plugins furtifs et des proxys résidentiels, contre un site qui a mis une protection
  devant ses données et `Disallow` dans son robots.txt. On respecte robots.txt — on ne le fera pas, et
  on le dira plutôt que de laisser croire le contraire
- Forme retenue : chaîne de renderers en config, **escalade sur le RÉSULTAT** et non par réglage
  d'hôte — fetch HTTP simple, et on ne réessaie via un renderer que si l'extraction est revenue
  `js_only`. Le verdict est écrit dans `known_hosts`, donc l'hôte suivant y va directement
- **Le sidecar est le chemin principal, une API hébergée l'alternative**, jamais l'inverse : ADR-006
  impose que la découverte tourne en self-hosted sans souscrire à quoi que ce soit. `browserless/chromium`
  en profil compose **optionnel**, désactivé par défaut — image ~1 Go, 200 à 500 Mo par page, 2 à 5 s
  par page contre ~200 ms. Sur un petit VPS c'est la machine entière
- Cloudflare Browser Rendering, ScrapingBee ou Zyte se branchent au même endroit pour qui préfère payer
  plutôt qu'exploiter Chromium. Optionnel, avec clé, jamais supposé

**5.8** ⬜ En tant qu'utilisateur, je veux une fiche contact centralisée.
- Historique d'outreach, statut de vérification, activité par campagne, provenance
- Société en objet séparé et dédupliqué, jamais recopiée sur chaque contact

### Epic 6 — Séquences & personnalisation `v0`

**6.1** ⬜ En tant qu'utilisateur, je veux que l'IA génère une séquence complète à partir du contexte projet.
- Séquence par défaut : email → attente → relance
- Générée en moins de 5 minutes, entièrement éditable avant activation

**6.2** ⬜ En tant qu'utilisateur, je veux une accroche personnalisée par lead.
- Construite à partir de la knowledge base + de la justification de fit de la société
- Aucune recherche manuelle par contact
- Prévisualisation sur un échantillon avant lancement

**6.3** ⬜ En tant qu'utilisateur, je veux composer les étapes moi-même.
- Types en v0 : email, attente. LinkedIn plus tard, même structure
- Réordonnancement, délais configurables

**6.4** ⬜ `v1` En tant qu'utilisateur, je veux plusieurs variantes par étape en A/B automatique.
- Répartition par poids, résultats par variante

**6.5** ⬜ `v1` En tant qu'utilisateur, je veux des variables et des blocs conditionnels dans mes templates.
- Variables sur les champs lead et société, avec valeur de repli obligatoire
- Blocs conditionnels : afficher un paragraphe seulement si un champ est renseigné
- Prévisualisation rendue sur un lead réel, et refus d'envoi si une variable ne résout pas

### Epic 7 — Envoi `v0`

**7.1** ⬜ En tant qu'utilisateur, je veux connecter un ou plusieurs comptes email SMTP/IMAP.
- Identifiants chiffrés avec `CREDENTIALS_KEY` (ADR-012) ; test de connexion à l'enregistrement
- Partagés entre projets ou dédiés à un projet, au choix
- **Pas d'OAuth** (ADR-027) — SMTP/IMAP classique uniquement
- **Le test de connexion nomme la cause exacte de l'échec** : app passwords désactivés par
  l'administrateur Workspace, SMTP AUTH coupé sur le tenant M365, port bloqué, TLS refusé…
- Une page de documentation par fournisseur courant (OVH, Infomaniak, Gandi, Zoho, Gmail, M365)

**7.2** ⬜ En tant qu'utilisateur, je veux une limite d'envoi quotidienne par compte.
- Le surplus est reporté au lendemain, la campagne ne s'arrête pas
- Envois répartis dans la journée, pas en rafale

**7.3** ⬜ `v1` En tant qu'utilisateur, je veux un ramp-up progressif sur un nouveau compte.
- Courbe de montée configurable, appliquée automatiquement

**7.4** ⬜ `v1` En tant qu'utilisateur, je veux que mes envois tournent sur plusieurs boîtes.
- Une campagne répartit ses envois sur un pool de comptes, chacun avec son propre plafond
- Un lead donné reste sur la même boîte pour toute la séquence, thread préservé
- Une boîte en échec ou en pause est retirée du pool sans interrompre la campagne
- *Boîtes illimitées sans surcoût : c'est précisément ce que facturent Instantly et Smartlead.*

**7.5** ~~Warm-up des boîtes~~ — **hors scope, décision assumée (ADR-023)**.
- Remplacé par une page de documentation expliquant pourquoi, et un point d'intégration tiers

**7.6** ⬜ En tant qu'utilisateur, je veux que tout envoi soit conforme.
- Phrase d'opt-out générée dans le corps ; « STOP » détecté → suppression immédiate (ADR-029)
- Aucun lien, aucune image, aucun en-tête révélant un outil — indiscernable d'un envoi manuel
- Suppression list vérifiée avant chaque envoi
- Hard bounce → suppression automatique

### Epic 8 — Réponses & inbox `v0`

**8.1** ⬜ En tant qu'utilisateur, je veux que la campagne se mette en pause sur un lead qui répond.
- Attribution par `Message-ID` / `In-Reply-To`
- Auto-reply détecté → **pas** de pause
- Pause visible avec son motif

**8.2** ⬜ En tant qu'utilisateur, je veux une inbox unifiée sur tous mes comptes email.
- Seuls les contacts ayant réellement répondu apparaissent
- Filtrable par projet et par campagne

**8.3** ⬜ En tant qu'utilisateur, je veux répondre depuis l'app.
- Réponse envoyée depuis le compte email d'origine, thread préservé

**8.4** ⬜ En tant qu'utilisateur, je veux voir l'état de chaque lead dans le pipeline.
- Vue funnel par étape, comptages par statut : en cours, terminé, répondu, échoué, supprimé

**8.5** ⬜ En tant qu'utilisateur, je veux un dashboard projet avec les stats clés.
- Campagnes actives, contacts, taux de réponse, activité récente, consommation IA

### Epic 9 — Organizations & permissions `v1` — **cœur, pas cloud**

> Disponible dans les deux éditions. `app/Cloud/` ne contient que facturation et crédits (ADR-025).

**9.1** ⬜ En tant qu'utilisateur cloud, je veux créer mon compte et devenir owner de mon organization.
**9.2** ⬜ En tant qu'admin, je veux inviter des membres avec un rôle.
**9.3** ⬜ En tant qu'admin, je veux accorder l'accès projet par projet.
- Un membre sans accès à un projet ne le voit pas, ne le devine pas via une URL, et reçoit un 404

### Epic 10 — Facturation `v1`

**10.1** ⬜ En tant qu'utilisateur cloud, je veux m'abonner par carte (Stripe).
**10.2** ⬜ En tant qu'utilisateur cloud, je veux voir ma consommation par rapport à mon plan.
- Leads découverts, emails envoyés, coût IA — ventilés par projet, alimentés par `agent_runs`
**10.3** ⬜ En tant qu'utilisateur self-hosted, je veux que le core reste gratuit sans limite artificielle.
- Le cloud ajoute uniquement : **hébergement géré, facturation, clé IA fournie, support**
- Le multi-utilisateur n'en fait **pas** partie — il est dans le cœur (ADR-025)

### Epic 11 — LinkedIn `plus tard`

**11.1** ⬜ Enchaîner visite / connexion / message dans une campagne, en parallèle des étapes email.
**11.2** ⬜ Se connecter à LinkedIn côté serveur, avec gestion des codes email/SMS et de l'approbation mobile.
**11.3** ⬜ Rythme humain : délais randomisés.
**11.4** ⬜ Empreinte navigateur stable dans le temps, y compris après un rebuild du container.

> *Sales Navigator* (mentionné dans la version précédente du doc) est l'abonnement LinkedIn payant pour
> commerciaux : on y construit une recherche filtrée dont l'URL encode les filtres, et « importer cette
> URL » signifie scraper les pages de résultats. Nécessite un compte Sales Nav actif plus toute la stack
> anti-détection. Même verdict que le reste de l'epic : plus tard.

### Epic 12 — Intégrations `plus tard`

**12.1** ⬜ API pour créer projets, leads et campagnes par programmation.
**12.2** ⬜ Serveur MCP pour piloter l'outil depuis un agent IA externe.
**12.3** ⬜ Webhooks pour brancher un CRM.

---

## 8. Hors scope explicite

À ne pas construire, même si la tentation est là :

- **Outil d'audit SEO complet.** L'agent Website produit une knowledge base ; les suggestions sont un
  sous-produit gratuit du même appel LLM.
- **Base de contacts propriétaire.** On cherche en live, on ne constitue pas de base à revendre.
  À dire clairement dans le produit : lemlist et Apollo embarquent une base de plusieurs centaines de
  millions de contacts, Eveil non et n'en aura jamais. Ce n'est pas un manque à combler, c'est un
  choix — la découverte en direct trouve du frais et du long tail (commerces locaux, boîtes récentes,
  annuaires de niche) que les bases achetées, périmées et anglo-centrées, ratent. Mais l'utilisateur
  qui veut filtrer 275M de lignes doit être orienté ailleurs plutôt que déçu.
- **Relais d'envoi ESP.** Voir ADR-005.
- **Tracking d'ouverture comme métrique centrale.** Apple Mail Privacy Protection a rendu les taux
  d'ouverture ininterprétables. La métrique qui compte est le taux de réponse.
- **CRM.** On s'y branche, on ne le remplace pas.
- **Gestionnaire de tâches.** Les propositions d'amélioration (ADR-032) ont un état, pas un backlog :
  l'agent le met à jour depuis la conversation. Dès qu'il faut ranger, assigner ou dater des tâches à
  la main, on a franchi la ligne.
- **Document de stratégie.** Une recommandation sans preuve vérifiable et sans exécution derrière est
  un conseil générique. Eveil trouve et contacte ; il ne rédige pas de plans.

---

## 9. Questions ouvertes — registre

Registre complet des points non tranchés, par échéance. Chaque entrée porte un identifiant stable
pour être référencée ailleurs, dit **ce qu'elle bloque**, et propose **l'option qui tient la corde**
quand il y en a une. Une question résolue devient un ADR en §3 et sort d'ici.

### Tier A — à trancher avant la première migration

Ces questions déterminent le schéma ou l'infra. Les trancher après coup coûte une migration de données.

~~**A1 — Moteur de base de données**~~ → tranché, voir **ADR-010**.

~~**A2 — Queue et workers**~~ → tranché, voir **ADR-011**.

~~**A3 — Chiffrement des credentials**~~ → tranché, voir **ADR-012**. Reste ouvert en niveau C :
chiffrement par organization et KMS externe en cloud.

~~**A4 — Périmètre de la suppression list**~~ → tranché, voir **ADR-013**.

~~**A5 — Sociétés et leads : cloisonnés ou partagés ?**~~ → tranché, voir **ADR-014**.

~~**A6 — Plusieurs profil cible par projet ?**~~ → tranché, voir **ADR-015**.

~~**A7 — Tracking d'ouverture et de clic**~~ → tranché, voir **ADR-016**.

~~**A8 — Rétention et purge RGPD**~~ → tranché, voir **ADR-018**.

**Tier A entièrement tranché** (ADR-010 à ADR-018). Le schéma et l'infra sont débloqués : les
migrations peuvent être écrites.

### Tier B — à trancher avant de dessiner les écrans v0

~~**B1 — Économie unitaire d'un discovery run**~~ → tranché, voir **ADR-019**. Coût mesuré ≈ 2,80 $
pour 100 leads qualifiés (≈ 0,03 $/lead) ; la grille de crédits ajustable en base rend l'affinage
non bloquant.

~~**B2 — Que fait l'agent quand la découverte ne trouve rien ?**~~ → tranché, voir **ADR-020**.

~~**B3 — Multi-langue**~~ → tranché, voir **ADR-021**.

~~**B4 — Quelle est la métrique de succès affichée ?**~~ → tranché, voir **ADR-022**.

~~**B5 — Warm-up des boîtes**~~ → tranché, voir **ADR-023** : on n'en fait pas, position documentée.

### Tier C — à trancher avant l'ouverture publique et le lancement cloud

~~**C1 — Prix de vente du crédit**~~ → tranché, voir **ADR-024**.

~~**C2 — CLA et modèle de contribution**~~ → tranché, voir **ADR-025**.

~~**C3 — Provider IA par défaut en cloud, et repli**~~ → tranché, voir **ADR-026**.

~~**C4 — IP sortantes en cloud**~~ → tranché, voir **ADR-027**. Le problème posé était faux (les IP de
datacenter ne sont pas bloquées) ; le vrai sujet était l'authentification, et la décision est de ne
faire que du SMTP/IMAP classique.

~~**C5 — Export et réversibilité**~~ → tranché, voir **ADR-028**.

~~**C6 — Formulation de la mention de provenance (RGPD art. 14)**~~ → tranché, voir **ADR-029** : rien
d'hébergé, rien de généré côté juridique, opt-out par phrase dans le corps.

~~**C7 — Jusqu'où va l'écart self-hosted / cloud ?**~~ → tranché par **ADR-025** : `app/Cloud/` ne
contient que facturation et crédits ; tout le reste, multi-utilisateur compris, est dans le cœur.
L'écart se réduit à l'hébergement géré, la facturation, la clé IA fournie et le support. B5 (warm-up)
n'ouvre plus d'écart puisqu'on n'en fait pas (ADR-023).

~~**C8 — Nom, domaine, marque**~~ → tranché, voir **ADR-030** : le nom reste Eveil, le domaine se
choisira plus tard.

---

### Registre vide

**Toutes les questions bloquantes sont tranchées** — tier A (ADR-010 à ADR-018), tier B (ADR-019 à
ADR-023), tier C (ADR-024 à ADR-030).

Restent deux **échéances**, non bloquantes pour le développement mais à ne pas découvrir la veille du
lancement :

- **Avant d'ouvrir le repo** : rédiger `ICLA.md`, `CCLA.md`, `CONTRIBUTING.md`, brancher un bot de
  vérification CLA, faire relire la licence et le CLA par un juriste (ADR-025).
- **Avant de communiquer** : choisir le domaine et faire une recherche d'antériorité de marque à
  l'EUIPO (ADR-030).

Toute nouvelle question ouverte se rajoute ici avec un identifiant, et sort du registre en devenant un
ADR en §3.

---

## 10. Stack technique

**Backend** — Laravel 13, PHP 8.4
- `laravel/ai` — agents et MCP. **Pré-1.0 (v0.10.3), breaking changes entre minors.** Version pinnée
  exacte, appels encapsulés dans nos propres classes de service pour qu'un upgrade ne touche qu'un endroit.
- `laravel/fortify` (v1.37) — authentification
- `laravel/boost`, `larastan`, `pint`, `pest` — outillage

**Frontend app** — Inertia v3 + Vue 3 + Nuxt UI v4
- Nuxt UI 4.10 déclare `@inertiajs/vue3: ^2 || ^3` en peer dependency : usage hors Nuxt officiellement
  supporté. Tailwind v4 déjà installé, requis par Nuxt UI 4.

**Frontend public (cloud)** — Blade + Tailwind. Partie SEO, pages publiques.

**Base de données** — PostgreSQL uniquement, y compris en test et en CI (ADR-010).

**Queue, cache, locks** — Redis + `laravel/horizon` (ADR-011).

**Infra** — docker compose : app, Horizon, scheduler, Postgres, Redis, SearXNG.
Container headless browser ajouté seulement si le taux d'échec sur sites JS-rendered le justifie.

**Licence** — AGPL-3.0 (ADR-001).
