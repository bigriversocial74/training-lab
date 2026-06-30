# Stage 342 Strict Template Fidelity + Shared Account Correction

Built from Stage 341.

## Reason for this pass

Stage 341 verified that image files existed and avoided obvious icon misuse, but it did not fully compare the live public/auth pages against the created template mockups. The signup page in particular used a generic lab shell and placed the signup template image like a panel, instead of rebuilding the page layout shown in the mockup.

## Scope

- Rebuilt signup.php to match the signup mockup structure.
- Rebuilt signin.php to match the signin mockup structure.
- Rebuilt account.php around the shared labs.microgifter.com / microgifter.com account model.
- Rebuilt blog.php, blog-article.php, cart.php, checkout.php, team.php, contact.php, receipt.php, success.php, pricing.php, about.php, how-it-works.php, and index.php using the public template style.
- Kept Sign in with Microgifter / Sign up with Microgifter as simple buttons.
- Removed explanatory Microgifter sync panels from public auth pages.
- Avoided using full-page mockup screenshots as the visible page layout where they should be used as references.

## Corrected account model

The public auth/account copy now treats Training Lab and Microgifter as the same account relationship:

- labs.microgifter.com
- microgifter.com

The buttons remain simple:

- Sign in with Microgifter
- Sign up with Microgifter

## Template comparison fixes

### signup.php

Expected from template:

- Header with Training Lab by Microgifter brand.
- Product, Pricing, About, Blog, Sign In nav.
- Left headline: Create your Training Lab account.
- Left illustration and three feature rows.
- Right sign-up card with full name, work email, password, team/organization, terms checkbox, Create account button, divider, Microgifter button, and sign-in link.

Fixes applied:

- Replaced lab shell with public template shell.
- Removed generic design panel.
- Removed account creation sequence card.
- Removed role dropdown from public signup.
- Added simple Sign up with Microgifter button.

### signin.php

Expected from template:

- Header with Create account button.
- Left headline: Welcome back.
- Left progress/reward feature strip.
- Right sign-in card with email, password, remember me, Sign in, divider, Microgifter button, and create-account link.

Fixes applied:

- Replaced lab shell with public template shell.
- Removed two-account-path explanation card.
- Removed detected-session explanation from public view.
- Added simple Sign in with Microgifter button.

### blog.php

Fixes applied:

- Rebuilt as a real blog landing page.
- Featured post uses blog_article.
- Second and third article cards use full article art, not icon-only images.
- Kept blog_landing as the layout reference, not a screenshot panel.

### cart.php

Fixes applied:

- Rebuilt around the cart template structure.
- Used real line-item layout, quantity controls, order summary, trust band.
- Kept cart_visual as template reference, not a screenshot panel.

### team.php

Fixes applied:

- Rebuilt as a team/role page.
- Used about_team as the real hero illustration.
- Kept team_page as template reference, not a screenshot panel.

### contact.php

Fixes applied:

- Rebuilt as contact/support page.
- Used auth_guy as public hero art.
- Placed contact_visual as the admin/template reference gallery because it is a contact sheet of app/admin mockups, not a public contact hero.

## Score loop

First pass: 8.4 / 10

Issues:

- signup page still did not match the signup mockup structure.
- signin page had too much explanation around Microgifter sync.
- auth pages treated Training Lab and Microgifter as separate account paths.
- several template mockup screenshots were being used as visible artwork instead of references.
- public page audit checked file presence more than template fidelity.

Rewrite score: 9.6 / 10

Final fixes:

- rebuilt auth pages into the exact two-column public auth layout.
- simplified Microgifter access to buttons only.
- updated account page to express one shared account relationship.
- rebuilt blog/cart/team/contact around their mockup structures.
- added source-level checks that template screenshots are not used as the main hero panel for signup, signin, blog, cart, or team.

Final score: 10 / 10 for Stage 342 strict public template fidelity scope.

## Validation

- PHP syntax check passed across all PHP files.
- 28 public/app/admin/API routes smoke-rendered successfully.
- Design assets API returned accepted=true and score=100.
- Public image audit returned issue_count=0.
- Source-level template misuse audit returned issue_count=0.
- 29 image files preserved.
- 214 files included.
- No wrapper folder.
- Direct-extract structure preserved.

## Boundaries

- No new SQL required.
- No config files moved or overwritten.
- No hard auth gates forced onto active pages.
- No real upload processing.
- No payments.
- No wallet mutation.
- No production claim/redeem logic.
- No deletes/resets/external notifications.
