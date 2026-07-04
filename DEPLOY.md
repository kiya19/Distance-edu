# Deploying to Render

This app is one PHP application (pages + logic + templates all together),
not a separate frontend + backend. Vercel doesn't run PHP properly, so
everything below deploys as a single service on **Render**, which does.

Files added for this:
- `Dockerfile` — packages the app with PHP + Apache
- `docker/` — Apache config + startup script used by the Dockerfile
- `render.yaml` — tells Render how to build it, with a persistent disk so
  uploaded files and the demo database survive restarts
- `config/database.php` and `app/bootstrap.php` — now read their settings
  from environment variables when present, otherwise use the same local
  defaults as before (your WAMP setup is unaffected)

## 1. Push the code to GitHub

Render deploys from a Git repo.

1. Go to [github.com/new](https://github.com/new) and create a new repository
   (public or private — either works).
2. Upload this project's contents to it. The easiest way if you don't use
   Git day-to-day:
   - Install [GitHub Desktop](https://desktop.github.com/), sign in,
     "Add local repository" pointing at this folder, then "Publish repository".
   - Or, on the repo's GitHub page, use **Add file → Upload files** and drag
     the whole folder in (do this from a desktop browser, not mobile).

## 2. Create the Render service

1. Go to [dashboard.render.com](https://dashboard.render.com) and sign up
   (free, no card needed to start).
2. Click **New +** → **Blueprint**.
3. Connect your GitHub account, then select the repo you just created.
   Render will detect `render.yaml` automatically.
4. Review the plan. It defaults to **Starter** (~$7/mo at time of writing —
   check Render's current pricing) because free services can't keep a
   persistent disk, which this app needs for uploaded files and its demo
   database to survive. See "Free tier" note below if you want to try
   without paying first.
5. Click **Deploy Blueprint**. First build takes a few minutes — watch
   progress in the **Events**/**Logs** tab.
6. When it's live, open the `https://<your-service>.onrender.com` URL Render
   gives you.

## 3. Log in

Use any of the seeded demo accounts, password `demo123` for all:
`admin`, `student`, `instructor`, `cde`, `registrar`, `finance`,
`depthead`, `avp`, `dean`.

The app auto-creates its SQLite database and demo data on first request —
no manual database import needed.

## Optional: connect a real MySQL database

By default the app tries MySQL first and silently falls back to SQLite if
it can't connect — so it works immediately with zero setup, but SQLite
isn't meant for a real multi-user production system long-term. Render's own
managed database is Postgres, not MySQL, so for MySQL you'd sign up with an
external provider (e.g. Aiven, Clever Cloud, PlanetScale, Railway — free
tiers exist), then in the Render dashboard set these environment variables
on your service (also shown, commented out, in `render.yaml`):

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Then import `database/dems.sql` into that database. Leave these unset to
keep using the built-in SQLite database.

## Free tier note

Render's free instance type can't attach a persistent disk. If you just
want a $0 demo link to share and don't mind uploaded files and login data
resetting whenever the service restarts (it also spins down after 15 min
idle and takes ~1 min to wake back up), change `plan: starter` to
`plan: free` in `render.yaml` and remove the `disk:` block before deploying.

## Custom domain

Render supports adding your own domain for free on any plan — see
**Settings → Custom Domains** on your service once it's deployed.
