# Running ads

Eveil can run your paid ads for you, inside a monthly budget you set on each ad account. Once a day it reviews every campaign's numbers, launches new tests, keeps what brings you users, cuts what does not, and tries what works on other networks. You decide how much it does on its own with the [Ads autonomy setting](/product/autonomy#ads). It never spends past the monthly budget, whatever that setting says.

Google Ads is supported today. Other networks will follow.

## How it works

1. **Connect an ad account** from the **Ads** page. Every account your sign-in can manage is listed, each with a monthly budget of zero: Eveil spends nothing on an account until you give it a budget, so giving it a budget is how you choose which accounts to use.
2. **Set a monthly budget** on the account you want Eveil to run.
3. **Eveil reviews it every morning**, or right away with **Review now**. Each move comes with its reasoning and the numbers behind it. In Supervised mode, anything that would spend more waits in **Waiting for you** at the top of the Ads page: approve it, or reject it with a reason, which Eveil reads at its next review. Pausing or lowering spend never waits.

Eveil only ever touches the campaigns it created. Whatever you already run by hand in the same account stays yours.

**Conversion tracking.** If your site carries the network's tag (the Google tag for Google Ads), Eveil aims at signups. Without it, it can only aim at clicks, which is a weaker signal: the Ads page tells you when an account has no tracking.

## Why you need your own app

Ad networks only let an approved developer app manage ads, and their quotas and restrictions apply to the app, not to your account. With your own app, nobody else's ads can get it restricted. On the hosted edition this step may already be done for you: if **Settings → Ad platforms** says **Using this instance's app**, skip straight to connecting.

The setup is once per organization. You end up with two values, a **client ID** and a **client secret**, which you paste in **Settings → Ad platforms**.

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
3. Back on the Ads page, set a monthly budget on the account Eveil should run.

The client secret is stored encrypted and never shown again. To change it, paste a new one; leaving the field blank keeps the current one.

### If something goes wrong

- **"redirect_uri_mismatch" when connecting.** The redirect URI in Google does not match Eveil's exactly. Copy it again from **Settings → Ad platforms**, including `https://` and with no trailing slash.
- **Connected, but no account is listed.** The Google account you signed in with has no direct access to an ad account. Accounts reached only through a manager (MCC) account are not supported yet: give your Google user direct access to the ad account in Google Ads, under **Admin → Access and security**.
- **An error mentioning `CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION`.** The project still has Test access only: apply for Explorer access (step 5), and check that the client ID comes from the same project.
- **"Reconnect needed" on an account a week later.** The consent screen is still in Testing: publish it (step 3), then connect again.
