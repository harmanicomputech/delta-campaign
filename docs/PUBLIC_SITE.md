# Public website and CMS

Phase 10. The public website is server-rendered Blade with Livewire forms; staff manage it from the
workspace. Nothing reaches the public site without review by a second person.

## Pages

| Path | Source |
|---|---|
| `/` | Hero (candidate name, office and tagline from `config/campaign.php` → `public`, all `[PLACEHOLDER: …]` until supplied), latest news, events, fact-checks, projects |
| `/about`, `/manifesto` | CMS pages with those slugs. Until one is published the page says "Coming soon" |
| `/projects`, `/projects/{slug}` | Published project records, filter by LGA and category |
| `/lgas`, `/lgas/{slug}` | LGA facts from the geography tables, plus that LGA's events, projects and news |
| `/news`, `/fact-checks`, `/news/feed.xml` | Published CMS items, RSS feed |
| `/events`, `/events/{slug}`, `/events/ticket/{token}` | Published, cancelled and completed events; registration; ticket |
| `/join`, `/volunteer`, `/polling-unit`, `/contact`, `/preferences`, `/privacy` | Forms and the current privacy notice |
| `/sitemap.xml`, `/robots.txt` | Generated; robots blocks everything outside production |

Every page sets a title, description, canonical URL, Open Graph and Twitter metadata. JSON-LD is added
for the organisation (home), news and fact-check articles (`NewsArticle`) and events (`Event`). The language switcher (English / Pidgin) is stored in the session and a cookie.

## Forms and anti-spam

Forms use `GuardsPublicForms`: a hidden honeypot field, a 3-second minimum fill time and per-IP hourly
limits (sign-up 5, contact 5, one-time codes 6). Phone numbers are proved with a one-time code
(`PublicVerification`, valid 30 minutes in the session). No third-party captcha, analytics or fonts.

Supporter sign-up records consent per channel against the current privacy notice. The contact form
encrypts the message and reply address at rest; staff read them in the contact inbox, and each opening
is audited.

## CMS workflow

`ContentService` (pages, news, fact-checks):

```
draft ──submit──▶ in review ──approve (not the author)──▶ approved ──publish──▶ published
  ▲                   │                                       └──schedule──▶ scheduled ──(time)──▶ published
  └──request changes──┘                     published ──withdraw──▶ withdrawn;  any non-live ──▶ archived
```

- Each save stores a version (`content_versions`); any version can be restored into a new draft.
- Publishing stores `published_snapshot`. Editing a live item creates a new draft while the public site
  keeps serving the snapshot until the new version is approved and published.
- Edits use optimistic locking (the version number); a stale save is refused.
- `content:publish-due` runs every minute and publishes scheduled items.
- Images are re-encoded with GD to WebP at 480/960/1600 px, which removes EXIF and location data.

Permissions: `content.view`, `content.create`, `content.review`, `content.publish`.

## Project directory

`ProjectService`: records need a source note and a verification status (not yet verified, documents checked,
site visited). They are approved by `projects.approve` holders other than the author; editing
a published record takes it off the site until approved again. Do not create records that are not backed
by a source.

## Events

`EventService`: organisers (`events.manage`) create events only inside their area. Registration takes a
row lock on the event to enforce capacity; the same number registering twice gets the same ticket. The
ticket page shows a QR code containing only the check-in URL with a random 40-character token, never a
name or number. Check-in (`/workspace/events/check-in/{token}`) shows the attendee and changes nothing
until the organiser presses "Check in"; a repeat scan reports the earlier time. Walk-ins can be added at
the door. Cancelling an event sends one SMS to every registrant.

## Rapid response

`RapidResponseService`: `new → investigating → (legal review) → awaiting approval → approved`, or closed /
no response. Evidence files are stored on the private disk with a SHA-256 hash; downloads are audited and
served with `nosniff` and a sandbox CSP. Source links are stored as text and only `http(s)` links are
kept. Approval (by someone other than the creator) only creates a draft fact-check, which then goes
through the normal CMS review.

## Sample data

`SamplePublicSiteSeeder` (part of `SampleDataSeeder`, never in production) creates About and Manifesto as
unpublished placeholder drafts and SAMPLE-flagged news, fact-check, project, event, rapid-response case
and contact message.
