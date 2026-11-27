# Ansofra CQRS in this Laravel application

Ansofra is implemented as a centralized responsibility structure inside Laravel. Business use cases live in `app/Services/<Domain>/Commands` for mutations and `Queries` for reads. Each use case exposes `execute()`. Controllers, queue jobs, scheduled Artisan commands, listeners, and webhook processing call these same use cases; there is no separate command bus or CQRS database.

## Responsibilities

- Routes and middleware define entry points and request-level controls.
- Controllers translate HTTP input and output; they contain no business workflow.
- Form Requests validate user input. DTOs carry structured use-case input where a typed boundary is useful.
- Commands and queries coordinate business operations and authorization.
- Repositories own persistence queries; Eloquent models express stored records and relationships.
- Policies and configured permissions enforce access on the server.
- Events describe committed business occurrences; listeners and jobs perform dependent work after commit.
- Provider API clients communicate with Meta and do not own CRM rules.

Use Laravel's container, transaction manager, event dispatcher, queue, scheduler, notifications, authentication and migrations. Keep reads and writes on the same PostgreSQL database. Add interfaces or abstractions only when there is a real substitution or testing boundary.

## Tenant and authorization invariant

The installation represents one configured shop. Resolve its active business context from the server and verify staff membership. Never use a request-supplied business identifier as proof of access. Commands callable outside a controller must still enforce relevant authorization or receive a trusted actor/context and validate it.

## Reliability invariant

Wrap critical state transitions in transactions, dispatch dependent work after commit, and make retried jobs idempotent. Do not keep database transactions open during provider HTTP calls. Immediately recheck consent and customer eligibility before sending a WhatsApp message.
