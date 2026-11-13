# Running ads

Eveil can run your paid ads for you, inside one monthly budget you set for the project. You do not split it between networks: Eveil does, and keeps moving it toward whatever brings you users for less. Once a day it reviews every campaign's numbers, launches new tests, keeps what brings you users, cuts what does not, and tries what works on other networks. You decide how much it does on its own with the [Ads autonomy setting](/product/autonomy#ads). It never spends past the monthly budget, whatever that setting says.

Three networks are supported: **Google Ads**, **Microsoft Advertising** (Bing, and Microsoft's other search results) and **Meta Ads** (Facebook and Instagram). Google and Microsoft run the same kind of campaign, search ads with responsive text ads, so a creative that works on one can be tried on the other. Meta shows link ads in people's feeds: one headline and one short text per ad, with the picture taken from your landing page's preview image. A creative written for search is rewritten to fit before it goes to Meta.

## How it works

1. **Connect your ad accounts** from the **Ads** page, one network or several. Every account your sign-in can manage is listed. When it reaches a single account, that one is switched on (**Use for ads**). When it reaches several, for example an agency login that also sees clients' accounts, they all start switched off: switch on the ones that belong to this product.
2. **Set the monthly budget** at the top of the Ads page: one amount for the whole project, every network together.
3. **Eveil reviews it every morning**, or right away with **Review now**. Each move comes with its reasoning and the numbers behind it. In Supervised mode, anything that would spend more waits in **Waiting for you** at the top of the Ads page: approve it, or reject it with a reason, which Eveil reads at its next review. Pausing or lowering spend never waits.

Eveil only ever touches the campaigns it created. Whatever you already run by hand in the same account stays yours.

**How the budget is spent.** Eveil paces the month: every running campaign, on every account, must be able to spend its daily budget every day left in the month inside what remains. It starts each network with a fair test, then moves money toward the one that brings users cheaper. Once the month is spent, or if you set the budget to zero, every campaign pauses at once. Switching an account off pauses what runs there.

**One currency.** The budget is set in the currency of one of your connected accounts, and only accounts billed in that currency are used: Eveil never converts between currencies. An account billed in another one shows **not used** on the Ads page.

**The month is the instance's.** Accounts can sit in different time zones, the budget has one: days and the month are counted in the Eveil instance's time zone.

**Conversion tracking.** If your site carries the network's tag (the Google tag for Google Ads, a UET tag with a conversion goal for Microsoft Advertising, the Meta Pixel for Meta Ads), Eveil aims at signups. Without it, it can only aim at clicks, which is a weaker signal: the Ads page tells you when an account has no tracking. On Meta, campaigns are always delivered for link clicks, and the Pixel's sign-ups (the **CompleteRegistration** and **Lead** events) are what Eveil judges them by.

## Why you need your own app

Ad networks only let an approved developer app manage ads, and their quotas and restrictions apply to the app, not to your account. With your own app, nobody else's ads can get it restricted. On the hosted edition this step may already be done for you: if **Settings → Ad platforms** says **Using this instance's app**, skip straight to connecting.

The setup is once per organization and per network. You end up with a **client ID** and a **client secret** (plus a **developer token** for Microsoft Advertising), which you paste in **Settings → Ad platforms**.

## Google Ads: getting a client ID and secret

You need a Google account that can sign in to your Google Ads account, and about fifteen minutes. Google no longer issues developer tokens (since September 2026): access to the Google Ads API now belongs to a Google Cloud project, and that is all you set up here.

### 1. Create a Google Cloud project

1. Open the [Google Cloud console](https://console.cloud.google.com/) with the Google account you use for Google Ads.
2. In the project picker at the top, choose **New project**, name it (for example "Eveil ads") and create it.
3. Make sure that project is selected for every step below. The client ID you create later must come from the same project that holds the API access, or Google treats your calls as test only.

### 2. Enable the Google Ads API

1. Go to **APIs & Services → Library**.
2. Search for **Google Ads API**, open it and click **Enable**.

### 3. Configure the consent screen

This is the screen you see when you connect your account from Eveil.

1. Go to **APIs & Services → OAuth consent screen** (Google also calls it **Google Auth Platform → Branding**).
2. Choose **External** as the user type, unless the project belongs to a Google Workspace organization and only its members will connect, in which case **Internal** is simpler.
3. Fill in the app name, your support email and the developer contact email. Nothing else is required.
4. Under **Data access** (or **Scopes**), add `https://www.googleapis.com/auth/adwords`.
5. Under **Audience**, click **Publish app** to move it to **In production**.

Publishing matters. An app left in **Testing** works, but Google expires its connection every 7 days, and Eveil would lose access to your account every week. A published app that Google has not verified works too: you see an "unverified app" warning once when connecting (click **Advanced**, then continue), and it is limited to 100 users, far more than you need. You do not need to submit the app for verification to run your own ads.

### 4. Create the OAuth client

1. Go to **APIs & Services → Credentials** (or **Google Auth Platform → Clients**), then **Create credentials → OAuth client ID**.
2. Application type: **Web application**. Name it "Eveil".
3. Under **Authorized redirect URIs**, add the **Redirect URI** shown for Google Ads in Eveil's **Settings → Ad platforms**, copied exactly. It looks like `https://your-eveil-domain/app/oauth/ads/google_ads/callback`.
4. Click **Create**. Google shows your **Client ID** and **Client secret**: copy both. You can find them again later under **Credentials**, by clicking the client's name.

Google accepts a plain `http://` redirect URI only for `localhost`. A self-hosted instance reached by any other address needs HTTPS for this to work.

### 5. Request production access to the Google Ads API

A new project can only reach Google's test accounts. Your real ad account needs at least **Explorer** access.

1. Go back to **APIs & Services → Library → Google Ads API** and open its **Overview** page (not the API Center inside Google Ads: requests made there are no longer processed).
2. Under the access level, apply for **Explorer** access. Google often grants it automatically, and it is enough for Eveil: it allows managing campaigns on your own accounts, up to 2,880 operations a day, far above what one account's daily review and hourly sync use.
3. If you see a note about billing, the project may need a billing account in good standing to be approved. The Google Ads API itself costs nothing.

You need an Owner or Editor role on the project to apply.

### 6. Paste them into Eveil and connect

1. In Eveil, open **Settings → Ad platforms**, paste the **Client ID** and **Client secret** under Google Ads, and save.
2. Open the **Ads** page and click **Connect Google Ads**. Sign in with the Google account that has access to your ad account and accept.
3. Back on the Ads page, check the account is switched on (**Use for ads**) and set the monthly budget if it is not set yet.

The client secret is stored encrypted and never shown again. To change it, paste a new one; leaving the field blank keeps the current one.

### If something goes wrong

- **"redirect_uri_mismatch" when connecting.** The redirect URI in Google does not match Eveil's exactly. Copy it again from **Settings → Ad platforms**, including `https://` and with no trailing slash.
- **Connected, but no account is listed.** The Google account you signed in with has no direct access to an ad account. Accounts reached only through a manager (MCC) account are not supported yet: give your Google user direct access to the ad account in Google Ads, under **Admin → Access and security**.
- **An error mentioning `CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION`.** The project still has Test access only: apply for Explorer access (step 5), and check that the client ID comes from the same project.
- **"Reconnect needed" on an account a week later.** The consent screen is still in Testing: publish it (step 3), then connect again.

## Microsoft Advertising: getting a client ID, secret and developer token

You need a Microsoft Advertising account and about ten minutes. Microsoft asks for two things: an app registered in Microsoft Entra ID (the client ID and secret, for signing in) and a developer token (for using the API at all).

### 1. Register the app in Microsoft Entra ID

1. Open [App registrations](https://go.microsoft.com/fwlink/?linkid=2083908) in the Azure portal. Microsoft requires a work or school account here: a personal Microsoft account can no longer register apps.
2. Click **New registration** and name it "Eveil".
3. Under **Supported account types**, choose **Any Entra ID Tenant + Personal Microsoft accounts**, so whichever account signs in to Microsoft Advertising can connect.
4. Under **Redirect URI**, choose **Web** and paste the **Redirect URI** shown for Microsoft Advertising in Eveil's **Settings → Ad platforms**, copied exactly. It looks like `https://your-eveil-domain/app/oauth/ads/microsoft_ads/callback`.
5. Click **Register**. On the app's **Overview** page, copy the **Application (client) ID**: that is the client ID.

### 2. Create the client secret

1. In the app, open **Certificates & secrets**, then **New client secret**.
2. Give it a description and pick an expiry, then **Add**.
3. Copy the secret's **Value** right away (not its ID): Microsoft never shows it again.

A secret expires on the date you picked. When it does, Eveil can no longer refresh its access: create a new secret and paste it in Eveil before that date.

### 3. Get a developer token

1. Sign in to [Microsoft Advertising](https://ads.microsoft.com/) and open **Settings → Developer settings** (or go straight to [ads.microsoft.com/cc/Settings/DevSettings](https://ads.microsoft.com/cc/Settings/DevSettings)).
2. Pick the user the token belongs to and click **Request token**.
3. Copy the token. One universal token covers every account you manage.

### 4. Paste them into Eveil and connect

1. In Eveil, open **Settings → Ad platforms**, paste the **Client ID**, **Client secret** and **Developer token** under Microsoft Advertising, and save.
2. Open the **Ads** page and click **Connect Microsoft Advertising**. Sign in with the Microsoft account that has access to your ad account and accept.
3. Back on the Ads page, check the account is switched on (**Use for ads**) and set the monthly budget if it is not set yet.

The secret and the token are stored encrypted and never shown again. To change one, paste a new value; leaving a field blank keeps the current one.

### Good to know

- **Numbers arrive with a short delay.** Microsoft builds performance reports on request, so each sync waits a few seconds for its report. If Microsoft takes longer than two minutes, that sync stops and the next one reads the same days again.

### If something goes wrong

- **An error about the redirect URI when connecting.** The redirect URI registered on the app does not match Eveil's exactly. Copy it again from **Settings → Ad platforms**, including `https://` and with no trailing slash, and check it is registered as a **Web** redirect URI.
- **"AADSTS50194" or a message about a single tenant.** The app was registered for your organization's accounts only: change **Supported account types** to include personal accounts and other tenants (step 1).
- **An error mentioning invalid credentials or the developer token.** Check the token was copied whole, and that it is a production token, not the sandbox one.
- **"Reconnect needed" on an account.** The client secret expired or was deleted: create a new one (step 2), paste it in Eveil, then connect again.

## Meta Ads: getting an app ID and secret

You need a Facebook account that manages your ad account and the Facebook Page your ads will show under, and about fifteen minutes. Every Meta ad is posted as a Facebook Page: it is the name and picture people see above the ad. If you have no Page yet, [create one](https://www.facebook.com/pages/create) first.

You do not need Meta's App Review or Business Verification. An app that only manages the ad accounts of the people who have a role on it can stay in **Development mode** for good, and that is all Eveil needs to run your own ads.

### 1. Create the app

1. Open [Meta for Developers](https://developers.facebook.com/apps/) with the Facebook account that manages your ads, and click **Create app**. If you have never used it, Meta first asks you to register as a developer.
2. Name it "Eveil" and give your contact email.
3. When Meta asks for a use case, choose **Create & manage ads with Marketing API**.
4. If Meta asks for a business portfolio, pick the one that owns your ad account, or skip it if you run ads from a personal ad account.
5. Finish creating the app. It starts in **Development mode**: leave it there.

### 2. Add the redirect URI

1. In the app's dashboard, open the login product's **Settings** (**Facebook Login for Business → Settings**, or **Facebook Login → Settings**, depending on what Meta added for your use case).
2. Under **Valid OAuth Redirect URIs**, paste the **Redirect URI** shown for Meta Ads in Eveil's **Settings → Ad platforms**, copied exactly. It looks like `https://your-eveil-domain/app/oauth/ads/meta_ads/callback`.
3. Save.

Meta only accepts HTTPS redirect URIs (plain `http://localhost` is allowed for testing). A self-hosted instance reached by any other address needs HTTPS for this to work.

### 3. Copy the app ID and secret

1. Open **App settings → Basic**.
2. Copy the **App ID**: that is the client ID.
3. Click **Show** next to **App secret** and copy it: that is the client secret.

### 4. Paste them into Eveil and connect

1. In Eveil, open **Settings → Ad platforms**, paste the **App ID** as the client ID and the **App secret** as the client secret under Meta Ads, and save.
2. Open the **Ads** page and click **Connect Meta Ads**. Sign in with the Facebook account that has a role on the app, and accept every permission Meta asks for: Eveil needs to manage your ads and to post them as your Page.
3. Back on the Ads page, look at **Ads posted as** under the account. If your Facebook account manages a single Page, it is already picked. If it manages several, pick the one that belongs to this product: the account cannot be switched on until you do.
4. Check the account is switched on (**Use for ads**) and set the monthly budget if it is not set yet.

The app secret is stored encrypted and never shown again. To change it, paste a new one; leaving the field blank keeps the current one.

### Good to know

- **Only people with a role on the app can connect.** That is what Development mode means: you, and anyone you add under **App roles** in the app dashboard. Someone else who manages the ad account must be added there before they can connect it.
- **Meta's connection lasts 60 days.** Eveil renews it on its own while it still works. If nobody touches the account for that long, or you change your Facebook password, the account shows **Reconnect needed**: connect it again.
- **Development mode is slower.** Meta allows an app in Development mode far fewer calls than an approved one. One account's daily review and hourly sync fit, but if Meta pauses the app for a few minutes, that sync is skipped and the next one reads the same days again.
- **Changing the Page** on the Ads page applies to the ads Eveil creates from then on. Ads already running keep the Page they were created with.

### If something goes wrong

- **"URL blocked" or a message about the redirect URI when connecting.** The redirect URI in the app does not match Eveil's exactly. Copy it again from **Settings → Ad platforms**, including `https://` and with no trailing slash.
- **"App not active" or "This app is not available".** You signed in with a Facebook account that has no role on the app. Sign in with the one that created it, or add yours under **App roles**.
- **Connected, but no account is listed.** The Facebook account you signed in with does not manage an ad account. Check it in [Meta Ads Manager](https://adsmanager.facebook.com/).
- **"No Facebook Page reachable" under the account.** Your Facebook account manages no Page, or you unticked it when Meta asked which Pages to share. Create a Page or give access to it, then connect again.
- **A launch fails with a message about the DSA beneficiary or payer.** Meta requires both for ads shown in the European Union. Eveil sends your project's name for both: check it is your real business name in **Settings → Project**.
