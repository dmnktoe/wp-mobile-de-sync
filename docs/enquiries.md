# Enquiries

[← README](../README.md)

- [The form](#the-form)
- [Status and replies](#status-and-replies)
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

Three guards keep the robots out, none of which a person notices: a honeypot
field, a form submitted faster than anyone could type it, and a limit of five
enquiries per visitor per hour. A submission that fails validation comes back
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
