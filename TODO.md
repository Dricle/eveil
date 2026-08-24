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
- [x] **Démarrer et mettre en pause depuis la liste**, plus un select enterré sur la page de la
      campagne : `PUT campaigns/{campaign}/status` a son propre contrôleur, parce que l'interrupteur
      est actionné depuis une ligne de liste où le nom n'est pas édité et où le reposter écraserait
      un renommage. Renommer et démarrer sont deux gestes distincts
- [x] **Dire QUAND, et pourquoi pas maintenant.** La page campagne portait un funnel et rien sur le
      temps : `next_action_at` existait en base et ne sortait nulle part. Elle affiche maintenant la
      prochaine échéance et la raison quand rien ne part (pas démarrée, hors fenêtre, quota du jour
      épuisé, délai entre deux mails d'une même adresse), le compteur envoyés/restants par boîte, et
      les cinquante premiers leads de la séquence avec leur étape, ce qui est réellement parti et
      leur échéance. La fenêtre est lue sur `DispatchDueSends::windowIsOpen()` et le délai sur
      `EmailAccount::readyAt()` : l'écran interroge la règle que le planificateur applique, il n'en
      garde pas une copie qui finirait par promettre un envoi qui n'aura pas lieu
- [x] **Approbation par société, et `autonomy_level` a enfin un lecteur.** `companies.approved_at`,
      une colonne et pas un statut : un statut voyage par RECOPIE vers les personnes, et une recopie
      ne peut pas atteindre une ligne qui n'existe pas encore, donc un contact trouvé la semaine
      suivante naîtrait non approuvé. Le lead lit la permission à travers sa société, comme
      `Lead::contactable()` lit déjà le statut de la sienne. Ce n'est pas un second chemin
      d'exclusion : `excluded()` reste unique, `approved_at` est une permission dans l'autre sens, et
      les deux doivent passer. `supervised` n'inscrit qu'au démarrage manuel, `semi_auto` inscrit les
      sociétés approuvées au fil de l'eau, `autonomous` n'attend l'accord de personne. Approuver
      **déclenche aussi la recherche de contacts** : approuver puis devoir cliquer une seconde fois
      est exactement le clic qu'on supprime. Bouton par ligne, bouton de lot sur la page, filtre
      « Awaiting approval » avec son compteur. Un lead SANS société passe partout : il vient d'un
      import fait à la main, il n'y a rien à approuver et l'exclure voudrait dire qu'une liste
      importée ne reçoit jamais rien
- [x] **Inscription continue** : `eveil:enrol-due` toutes les cinq minutes. Une campagne ne regardait
      qu'une fois, au démarrage, et toute personne trouvée après restait dehors pour toujours. Or
      l'extraction de contacts arrive par vagues, donc « après » est le cas normal. Idempotent par
      construction (`whereDoesntHave` sur les adhésions vivantes, plus l'index unique)
- [x] **L'inscription respecte le profil cible de la campagne.** Elle prenait TOUS les leads
      contactables du projet en ignorant `campaigns.target_profile_id` et `company_target_evaluations`.
      Avec une seule campagne ça ne se voit pas ; avec deux, un lead trouvé par le profil partenaire
      reçoit la séquence client, et l'accroche est écrite depuis le `fit_reason` d'un profil dont la
      séquence ne parle pas
- [x] **Les adresses non confirmées partent en dernier**, et les devinées ne sont gardées que si le
      serveur les CONFIRME. `guessGeneric` acceptait `valid` **ou** `risky`, or `risky` veut dire
      catch-all ou serveur muet, c'est à dire « pas réfuté », pas « existe ». Sur un segment hébergé
      chez un fournisseur qui refuse les probes, plus rien n'est deviné : l'étape se désactive au
      lieu de faire semblant. Et l'ordre d'inscription met les nominatifs devant, les devinées
      derrière, pour que le coupe-circuit à 5 % de bounces se déclenche après que les bonnes adresses
      sont parties, pas à leur place
- [x] **Le mail s'adapte à qui le reçoit** : `email_source` et la partie locale de l'adresse partent
      dans le contexte de `message-personalizer`, avec la consigne qu'un prénom qui n'est pas un
      prénom (« Team », « Service », le nom de la société) vaut absence de prénom, et qu'une adresse
      générique est une boîte partagée à qui on écrit en tant que société. C'est l'agent qui tranche,
      pas une liste de prénoms en PHP qui serait fausse dès le premier client polonais
- [x] **Le cran d'autonomie se règle**, dans les réglages du projet, avec une ligne d'aide qui dit ce
      que chaque cran FAIT plutôt que son nom. Le lecteur avait été câblé avant l'écrivain : seule la
      valeur par défaut de la colonne pouvait le poser. Premier morceau de 13.2, côté projet
- [x] **Un segment sans séquence est signalé, et se comble en un clic.** Ce qui manque n'apparaît
      jamais sur une liste de ce qui existe : un profil cible sans campagne est un segment que les
      recherches continuent de remplir de sociétés que personne n'écrira jamais. Bandeau qui les
      nomme, bouton « Write the N missing » à côté du générateur par segment, et
      `eveil:write-missing` toutes les heures sur les projets `autonomous`. Horaire et pas toutes les
      cinq minutes : écrire trois mails est l'appel le plus cher du produit, et les segments
      apparaissent une ou deux fois dans la vie d'un projet. Garde-fou : rien n'est mis en file tant
      qu'une écriture est en vol, ni si le projet n'a pas encore de knowledge base, sinon c'est un
      job par profil pour lever la même erreur à chaque passage
- [x] **La page campagne se navigue par onglets**, comme un profil cible : « Sequence » (les mails,
      l'éditeur d'étapes, la prévisualisation) et « Delivery » (`/app/campaigns/{id}/delivery` : le
      funnel, la raison quand rien ne part, les quotas par boîte, les leads de la séquence). Un
      `CampaignHeader` partagé porte le nom, l'interrupteur, le segment et la suppression, avec le
      `UNavigationMenu` **dans le contenu** et jamais dans la barre d'app. Les deux se lisent à des
      moments différents, et un seul écran obligeait à scroller par dessus le run pour éditer un mail
- [x] **Un refus portant sur l'EXPÉDITEUR n'est plus lu comme une adresse morte.** Zoho répond
      « 553 Sender is not allowed to relay emails » quand l'adresse From n'est pas vérifiée sur le
      compte, et 553 était dans la liste des codes destinataire : le prospect était supprimé
      définitivement, le message compté comme bounce, et le coupe-circuit mettait la boîte en pause
      pour une faute qui était à un réglage de là. Les refus qui parlent de l'expéditeur (relay,
      sender rejected, not owned by user) sont testés AVANT les codes destinataire, parce que les
      deux erreurs ne coûtent pas la même chose : une boîte mise en pause à tort affiche les mots du
      serveur et se répare en un clic, une adresse supprimée à tort est silencieuse et censée être
      définitive
- [x] **Une boîte qui s'est arrêtée le dit sur tous les écrans**, à côté des deux bandeaux existants.
      Une boîte en `error` ou `paused` n'est pas manquante, elle est cassée, et rien nulle part ne le
      signalait : la campagne restait active, la séquence restait due, et le run ne bougeait
      simplement jamais. La phrase du serveur est reprise **mot pour mot**, parce que c'est elle qui
      nomme le réglage à changer : « 553 Sender is not allowed to relay emails » dit quoi faire, une
      paraphrase non. Scopé au projet courant, donc la boîte d'un autre projet n'apparaît pas
- [x] **Le bouton Test envoie un vrai message**, à l'adresse elle-même. Mesuré contre Zoho : un
      `MAIL FROM:<pas-a-moi@example.com>` reçoit `250 Sender OK`, et le refus n'arrive qu'après le
      `DATA`, parce que ce qui est vérifié est l'en-tête `From:` et pas l'enveloppe. Un test qui
      s'arrête avant DATA déclare fonctionnelle une boîte incapable d'envoyer quoi que ce soit, ce
      qui est pire que pas de test. Adressé à elle-même, donc rien ne part chez personne et
      l'arrivée du message est sa propre preuve. `explain()` a sa branche pour ce cas : « le login
      marche, mais le serveur refuse d'envoyer en tant que X, il faut un domaine vérifié ou un alias
      de Y »
- [x] **Le Message-ID est stocké nu, sans chevrons.** Les chevrons appartiennent à la syntaxe de
      l'en-tête, pas à l'identifiant, et un `In-Reply-To` entrant arrive déjà dépouillé. L'envoi
      stockait la forme entre chevrons, donc **aucune réponse n'a jamais été attribuée** : le lookup
      cherchait un id que rien ne portait, l'inbox restait vide et aucune séquence ne se mettait en
      pause sur une réponse. La comparaison retrime aussi à l'entrée, pour le serveur qui laisserait
      les chevrons. Migration pour normaliser l'existant, non réversible : remettre les chevrons
      remettrait le bug. Le test de non-régression prend l'id du **vrai** `Sender` au lieu d'une
      valeur choisie à la main, ce qui est exactement ce qui masquait le bug : les deux côtés de
      chaque fixture étaient écrits par la même main
- [x] **L'inbox a deux listes : « Replies » et « Sent ».** Ce qui est parti sans réponse n'était
      visible nulle part : il fallait ouvrir les fiches contact une par une pour répondre à
      « est-ce que quelque chose est vraiment sorti ». Deux listes et pas une seule, parce que
      mélanger mettrait cinq cents lignes muettes autour des quatre qui demandent une personne, ce
      que l'inbox existe précisément pour éviter. Les deux compteurs sont toujours calculés, donc
      l'onglet fermé dit quand même s'il contient quelque chose. Répondre à la main depuis « Sent »
      arrête la séquence, comme depuis « Replies », et le bouton le dit. Un envoi **refusé** y figure
      aussi, avec son badge : la tentative est un fait qui mérite d'être gardé, et le cacher dirait
      qu'il ne s'est rien passé alors qu'il s'est passé quelque chose. Mais il ne doit jamais avoir
      l'air d'un mail arrivé
- [x] **« Everything on openai » en un clic** sur `/app/app-settings/agents`. Le premier geste de
      quiconque n'utilise pas le fournisseur livré était de changer huit lignes une par une, chacune
      demandant d'aller chercher un identifiant de modèle ailleurs : c'est là qu'un écran de réglages
      perd les gens. Chaque agent garde son **timeout** (un modèle qui réfléchit meurt sur les 60 s
      par défaut, quel que soit le fournisseur) et atterrit sur le modèle de **même palier** chez le
      nouveau : celui qui était sur le plus intelligent y reste, celui qui était sur le moins cher
      aussi, et sinon c'est le défaut annoncé par le fournisseur. Le modèle est **remplacé** et pas
      fusionné, contrairement à `save()` : un `claude-opus-5` transporté chez OpenAI donnerait un
      mapping qui a l'air configuré et ne peut pas marcher. Seuls les fournisseurs avec une clé sont
      proposés, et un POST sur un autre est refusé en 422 plutôt qu'enregistré
- [x] **Plus aucun champ de formulaire sur `default-value`.** Nuxt UI lit cette prop **une fois**, au
      montage (`useVModel` sans `passive`), et Vue patche ensuite la `value` d'un champ en la
      comparant à ce que le DOM contient, pas au rendu précédent : chaque re-render réécrit donc la
      valeur figée par dessus ce qui a été tapé. Vu sur l'onboarding, trois réponses enregistrées et
      trois cases vides sous un compteur qui disait « 3 of 3 ». Les cinq autres écrans avaient le
      même défaut, dont `settings/KnowledgeBase.vue` qui poll toutes les 3 s pendant une analyse et
      écrasait donc la saisie trois fois par minute. Tous tiennent maintenant un brouillon
      synchronisé sur les props, comme `app-settings/Agents.vue` le faisait déjà. Pas de correctif
      par `:key` changeant : ça remonte le champ et fait perdre le curseur en pleine frappe
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
- [x] Les questions laissées ouvertes par la lecture du site sont **posées et répondables**, dans le
      run guidé juste avant la validation du portrait et sur `/app/settings/knowledge-base`. Chacune
      porte une clé stable, donc une relecture qui reformule ne redemande pas une réponse déjà
      donnée, et une question répondue survit même si le site la couvre désormais. Trois au plus, et
      seulement celles dont la réponse change qui est visé ou ce que dit le mail : répondre reste
      facultatif. Répondre n'écrit **pas** `knowledge_base_edited_by_user` : c'est un ajout, pas une
      correction, et geler tout le portrait coûterait trop cher pour une ligne tapée

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
- Découverte continue (`eveil:discover-due`, tourne déjà) attend son portefeuille ici, rien de plus :
  le garde-fou passe par `SpendGuardInterface`, déjà consulté à CHAQUE appel agent, découverte comprise.
  Brancher le portefeuille cloud suffit ; aucun câblage spécifique à la découverte à écrire. Les plafonds
  `daily_lead_limit` / `lead_limit` par projet restent en plus, dans les deux éditions : ADR-019 ne les
  remplace pas.

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
- [ ] Profil cible proposé par l'agent lui-même, depuis un signal rencontré en cours de découverte
      (le cas ADR-031 des intermédiaires, mais automatique plutôt que remarqué par un humain), lancé
      SANS validation même en profil supervisé (vision produit: l'app grandit seule, le seul frein est
      le budget). Reste à concevoir : le seuil de confiance avant de créer le profil, pour ne pas
      brûler du budget sur un pattern mal lu par le modèle.

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
