=== EIU Research Publication ===
Contributors:       christianmanaoat
Tags:               research, publication, peer-review, academic, journal
Requires at least:  5.8
Tested up to:       6.7
Requires PHP:       7.4
Stable tag:         1.2.0
License:            2021-989820-PH
License URI:        https://eiu.ac/license

Enterprise-grade academic research publication platform with article submission,
peer review workflows, reviewer management, and full administrative control.

== Description ==

EIU Research Publication is a professional WordPress plugin designed to power
academic journal and research publication platforms. Developed by Christian Manaoat
for the EIU IT Department.

= Core Features =

**Article Management**
* Frontend article submission form (PDF / PPT upload)
* Article categorization by subject
* Full article status lifecycle (Pending → Under Review → Approved → Published)
* DOI and ISSN support
* Customizable article detail pages

**Reviewer Management**
* Dedicated Reviewer user role
* Reviewer self-registration with email verification
* Admin manual verification
* Reviewer profiles with specialization tracking
* Manual and bulk reviewer assignment

**Review Workflow**
* Structured review submission forms (Accept / Minor / Major / Reject)
* Admin moderation: approve or reject submitted reviews
* Admin ability to delete improper reviews
* Due-date tracking per review assignment

**Notification System**
* Automatic HTML emails via wp_mail
* New submission → admin notification + author confirmation
* Status change → author notification
* Reviewer assignment → reviewer notification
* Review submission → admin notification
* Customizable email templates via filters

**Reporting & Monitoring**
* Full activity log (admin-level, all actions recorded)
* Dashboard analytics with Chart.js charts
* Submission trend chart (6 / 12 months)
* Articles by status doughnut chart
* Submissions by subject table
* Reviewer performance table

**Admin UX**
* Welcome / monitoring disclaimer screen (GDPR-style acknowledgement)
* Clean, structured admin panel following WordPress UI standards
* Centralized dashboard
* Filters, search, pagination on all list pages

**Security**
* Nonce verification on all forms
* Role-based access control (custom capabilities)
* Data sanitization & validation on all inputs
* Secure file uploads (MIME verification, randomised filenames, .htaccess protection)
* Rate limiting on public form submissions
* XSS, CSRF, SQL Injection protection
* Security HTTP headers

**Developer-Friendly**
* Fully OOP & namespaced (EIU_RP\\)
* PSR-4-style autoloader
* Theme template overrides (theme/eiu-rp/...)
* Extensive action/filter hooks
* Compatible with Elementor, Gutenberg, WPBakery, and all major page builders
* Multisite compatible

= Shortcodes =

`[eiu_submission_form]` – Renders the article submission form.
`[eiu_reviewer_dashboard]` – Renders the reviewer's personal dashboard (logged-in only).
`[eiu_article_list]` – Renders a list of published articles.

Attributes for `[eiu_article_list]`:
* `per_page` – Articles per page (default: 10)
* `subject`  – Filter by subject slug
* `status`   – Filter by status (default: published)

== Installation ==

1. Upload the `eiu-research-publication` folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins > Installed Plugins**.
3. Accept the monitoring disclaimer on first load.
4. Navigate to **EIU Research** in the admin menu.
5. Configure settings under **EIU Research > Settings**.
6. Add the shortcodes to your desired pages, or use the auto-created pages.

== Frequently Asked Questions ==

= Can I override templates? =
Yes. Copy any template from `templates/` to `{your-theme}/eiu-rp/` and customise freely.

= How do I add custom email templates? =
Use the `eiu_rp_email_body_{type}` filters. For example:
`add_filter( 'eiu_rp_email_body_article_submitted', 'my_custom_template', 10, 3 );`

= Is it compatible with Elementor? =
Yes. The plugin uses namespaced CSS classes and does not load any scripts on non-plugin pages.

= Can I customise the subject list? =
Yes. Go to **EIU Research > Settings > Subjects** and edit the list.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade path required.

== Developer Information ==

* Plugin:     EIU Research Publication
* Version:    1.0.0
* Author:     EIU IT Department
* Developer:  Christian Manaoat
* Contact:    support@eiu.ac
* Website:    https://eiu.ac
* License:    2021-989820-PH
