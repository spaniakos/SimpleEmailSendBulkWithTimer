# Delivery feedback

SMTP acceptance and final delivery are different events. A recipient marked **Sent** means the SMTP relay accepted the message. It does not prove that the recipient's provider put the message in the inbox. A later DSN can mark a message as delivered, deferred, bounced, or rejected; an ARF feedback report can mark a complaint.

## Supported reports

- RFC 3464 delivery status notifications (`message/delivery-status`).
- Abuse Reporting Format feedback reports (`message/feedback-report`).
- Reports are correlated through a cryptographically random `Message-ID` generated separately for every recipient.
- Hard bounces and abuse complaints suppress that contact across all lists and future campaigns. Soft bounces are recorded without automatic retries or global suppression. SMTP policy rejections are recorded without treating the address as invalid.
- Duplicate reports are idempotent. Unmatched or malformed reports do not change data.
- The application stores only event type, a bounded diagnostic string, report timestamp, and correlation. It never stores the raw report body.

## Route reports to the application

The application does not poll an inbound mailbox or store mailbox credentials. Configure the envelope sender/return path used by the SMTP relay to reach a bounce mailbox or alias on an MTA you control. Route each complete RFC 5322 message to this command on standard input:

```sh
php /srv/mailscript/artisan email:ingest-delivery-report
```

For a local MTA pipe, run the command as a dedicated unprivileged account that can read the application's environment/configuration and connect to its database. Do not expose the command over HTTP or run it as root. Have the MTA deliver the raw report through stdin; do not put report contents into command-line arguments or logs. The command reads at most 1 MiB and returns success for unsupported or unmatched mail so ordinary messages cannot cause a retry loop.

Exact alias and pipe syntax depends on the MTA and SMTP relay. Verify that your relay forwards RFC 3464/ARF reports with the original `Message-ID` intact. Some relays rewrite bounce addresses or omit correlation headers; those reports will remain unmatched and are ignored. A server that cannot route inbound reports can still send campaigns, but post-send feedback will not be available.

## What the metrics mean

- **Sent / accepted by SMTP**: the relay accepted the individual message.
- **Delivered**: an explicit positive DSN was received. Many providers do not send these, so this count may stay at zero even when mail reaches inboxes.
- **Deferred / soft bounce**: temporary delivery failure. No retry is attempted because retrying an already accepted message can duplicate mail.
- **Hard bounce**: permanent delivery failure; the contact is suppressed from future campaign sends.
- **Rejected**: a post-acceptance policy rejection, such as a 5.7.x DSN. It is shown but does not automatically suppress the address.
- **Complaint**: an ARF abuse report; the contact is suppressed from future campaign sends.

The report itself is untrusted input. The parser has a strict size cap, uses a maintained MIME parser, ignores unsupported/malformed input, and stores no raw message. Message IDs are recipient-specific, so a report cannot be applied to a different recipient's send record.
