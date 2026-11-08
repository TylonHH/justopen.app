# Gmail links in n8n → Telegram

Deploy the app-link version of this repository first. Replace the placeholder `https://go.example.com` below with **your own deployed HTTPS origin**, not the upstream public site. `/apps` lets you check the available routes.

In the Telegram **Send a text message** node, keep **Parse Mode = HTML** and turn off link previews. Use the full Gmail Trigger output (`Simplify = false`). This is a message-template example, not an automatically deployed n8n change:

```text
📧 E-Mail als Rechnung markiert

{{ ($('Gmail Trigger').item.json.headers.from || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') }}
{{ ($('Gmail Trigger').item.json.headers.subject || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') }}

<a href="https://go.example.com/gmail?mid={{ encodeURIComponent($('Gmail Trigger').item.json.messageId) }}">📥 In Gmail öffnen</a>
```

An inline URL button can use the same HTTPS URL. Its URL field is not HTML, so do not HTML-escape query separators there. If the notification is only a probabilistic screening result, use “Möglicher Rechnungsanhang” instead of claiming a confirmed invoice.

The landing page offers:

- A Gmail inbox-opening button on iOS and Android.
- A copyable `rfc822msgid:…` search to paste into the correct Gmail account.
- An exact Gmail web search.
- An explicitly experimental `googlegmail:///search?q=…` link on iOS. This is not a documented, reliable way to open a specific message.

Optional account selection for the web fallback:

```text
<a href="https://go.example.com/gmail?mid={{ encodeURIComponent($('Gmail Trigger').item.json.messageId) }}&amp;account={{ encodeURIComponent('you@gmail.com') }}">📥 In Gmail öffnen</a>
```

Use the actual signed-in Google account, not necessarily the recipient's plus-address alias. This account parameter does not switch the native Gmail app's account. Do not substitute Gmail `id`/`threadId` for the RFC `messageId`.

No additional HTTP Request node or per-message short-link creation call is needed. n8n constructs the HTTPS link directly. Generating an app link does not confirm that the email attachment contains an invoice.
