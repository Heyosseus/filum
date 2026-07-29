# Security Policy

## Supported versions

The latest minor release receives security fixes.

## Reporting a vulnerability

Please report security issues privately to **ratiruxadzee@gmail.com** rather than
opening a public issue. Include a description, affected versions, and steps to
reproduce if you have them. You can expect an acknowledgement within a few days.

## Notes on Filum's threat surface

Filum stores and displays messages between authenticated admin users. Two things
are worth knowing when assessing it:

- **One gate governs every surface.** The page, the overlay and both broadcast
  channels all defer to the same `Filum::auth` callback, and channel
  authorization additionally verifies conversation participation. A user cannot
  subscribe to a conversation the page would not have shown them.
- **Broadcast payloads carry ids, not content.** A `MessageSent` event carries the
  message id and conversation id only; subscribers re-read through the same
  authorized query the page uses. A misconfigured broadcaster therefore cannot
  leak message bodies.

Message bodies are escaped on render as ordinary Blade output. Filum does not
render user-supplied HTML or Markdown.
