# Enquiries

[← README](../README.md)

- [The form](#the-form)
- [Status and replies](#status-and-replies)
- [Spam](#spam)
- [Settings](#settings)
- [Contact Form 7](#contact-form-7)
- [Mail delivery](#mail-delivery)

## The form

Every vehicle page carries a form. A submitted enquiry goes out as e-mail with
the vehicle it is about — title, listing number, price and a link back — and
its `Reply-To` is the enquirer, so hitting reply answers the person rather
than the website.

It is **also stored** under *Vehicles → Enquiries*, together with whether the
mail was handed over and to whom. Mail is the part of this that fails: a wrong
SMTP setting, a full mailbox, a spam filter. An enquiry that only ever existed
as an e-mail is gone when that e-mail is. One that was written down is not.

Four guards keep the robots out, none of which a person notices: a honeypot
field, a form submitted faster than anyone could type it, a limit of five
enquiries per visitor per hour and a proof of work (see [Spam](#spam)). A
submission that fails validation comes back
with what was typed still in the fields and the error next to the one that is
wrong.

```text
[vehicle-enquiry]
[vehicle-enquiry vehicle="123" heading="Ask us about this car"]
```

## Status and replies

Each enquiry is **new** until somebody marks it answered — in the list, or in
the enquiry itself. The number of unanswered enquiries sits next to the menu
entry, on the *Enquiries* tab and on the overview.

**Reply by e-mail** opens the mail program with the enquirer's address, a
subject naming the vehicle and their message quoted.

The privacy tools of WordPress (*Tools → Export / Erase Personal Data*) find
stored enquiries by e-mail address.

## Spam

Two kinds get through a honeypot and a clock, and each has its own answer.

**Scripts** that post the form straight to the server are stopped by a
**proof of work** in the format of [ALTCHA](https://altcha.org/). When a
visitor starts typing, the browser fetches a puzzle from the site — a hash and
the range the secret number lies in — and counts until it finds the number,
which takes well under a second on a phone. The answer goes along in a hidden
field; the site checks it with one hash and a signature, accepts it once and
for thirty minutes. Nothing is loaded from elsewhere and no cookie is set, so
there is no service to name in the privacy policy. The cost: the form can only
be sent with JavaScript, and says so to a browser without it. A theme's own
copy of `parts/enquiry-form.php` does not need changing; the script adds the
field it needs.

**Advertising typed by a person** — "would you call me back within five
minutes?" — is recognised by what it says:

- a word from the list: `unsubscribe`, `opt out`, `seo`, `backlinks` and a few
  more are built in, the dealer adds their own;
- three or more links in the message;
- a sender on the **blocklist**.

Such an enquiry is stored with the status **spam** and the reason, mailed to
nobody — no mail to the dealer, no confirmation to the sender — and the sender
sees the usual thank-you. Spam is kept out of the list and has a view of its
own; after 30 days it is deleted.

**Mark as spam** — in the list, as a bulk action or in the enquiry — puts the
sender on the blocklist: a company by its domain, someone at gmail.com, web.de
and the like only by their address. **Not spam** moves the enquiry back and
takes the sender off the list again; it is not mailed afterwards.

An enquiry whose mail did **not go out** exists only on the site. Until it is
answered, a notice on the dashboard, the plugin screen and the list says how
many there are, and the list has a view *Not delivered* for them.

| Filter | |
|---|---|
| `wmds_spam_words` | the words checked, built-in and configured |
| `wmds_enquiry_spam` | the last word: return a reason to treat an enquiry as spam, or `''` to let it through |

## Settings

*Vehicles → Sync Settings → Enquiries*:

| Setting | |
|---|---|
| Enquiry form | show it on every vehicle page, or only where `[vehicle-enquiry]` is placed |
| Send to | one or more addresses; empty means the site administrator |
| Also to the seller | a copy to the address the feed carries for that vehicle |
| Confirmation | send the enquirer a copy of their message |
| Keep a record | store every enquiry in the database |
| Privacy notice | the text next to a checkbox the visitor has to tick; empty means no checkbox |
| Proof of work | the puzzle described under [Spam](#spam); on by default |
| Spam words | one per line, on top of the built-in ones |
| Blocked senders | addresses and `@domains`, one per line; *Mark as spam* adds to it |

`wmds_enquiry_recipients` has the last word on where an enquiry goes.

## Contact Form 7

A dealer who already has a form does not want a second one — the bundled
form can be switched off. What it knows and Contact Form 7 does not is which
vehicle the visitor is looking at.

Put a mail tag into the **mail template** of the form. No field in the form is
needed: the value is read from the page the form was submitted from.

| Mail tag | Value |
|---|---|
| `[_wmds_vehicle_title]` | the title |
| `[_wmds_vehicle_url]` | the address of the vehicle page |
| `[_wmds_vehicle_listing]` | the listing number |
| `[_wmds_vehicle_price]` | the price, as it is printed |
| `[_wmds_vehicle_make]`, `[_wmds_vehicle_model]` | make and model |
| `[_wmds_vehicle_year]`, `[_wmds_vehicle_mileage]` | first registration, mileage |
| `[_wmds_vehicle_fuel]`, `[_wmds_vehicle_gearbox]` | fuel, transmission |
| `[_wmds_vehicle_seller]`, `[_wmds_vehicle_seller_email]`, `[_wmds_vehicle_seller_phone]` | the seller the feed names |
| `[_wmds_vehicle_id]` | the post ID |

The same values are available as a form tag where one should travel with the
submission:

```text
[wmds_vehicle vehicle field:price]
```

A submission sent **from a vehicle page** is stored under *Vehicles →
Enquiries* like any other. Name, address, phone number and message are read
from the form under whatever names it uses. Submissions from any other page
are left alone. Optionally the address the feed carries for the vehicle is
added as a recipient — to the mail that goes to you, never to the confirmation
the visitor receives. These settings sit under *Embedding*.

## Mail delivery

Enquiries and alerts are sent with `wp_mail()`. Without an SMTP plugin that
means PHP's `mail()`: unauthenticated, sent from whatever address the server
decides on, and accepted by the local sendmail whether or not anything is ever
delivered. On a domain with a strict DMARC policy such mail is rejected
outright.

The *Enquiries* tab names the mailer plugin that is installed — WP Mail SMTP,
FluentSMTP, Post SMTP, Easy WP SMTP, WP Offload SES, Mailgun, Brevo — and,
where it can be read, what it has been told to send through. A plugin installed
but left on `mail` is called out, because that is the setting that looks solved
and is not.

The last delivery failure WordPress reported is kept and shown with the time it
happened, for **every** message the site sends rather than only ours. It
clears itself as soon as the plugin sends one successfully.

**Send a test mail** sends one mail to every address the plugin writes to — the
enquiry address and, when alerts are on, the alert address — and names them.
