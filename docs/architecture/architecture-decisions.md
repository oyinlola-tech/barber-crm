# Architecture decisions

## One Laravel application

The owner and staff use React through Inertia in the same Laravel repository. This keeps browser navigation, server validation, sessions, policies, and page data on one same-origin stack. There is no independent frontend application or general REST API for Inertia pages.

## One PostgreSQL database and database-backed infrastructure

PostgreSQL is the application store and Laravel database driver for queue, cache, and sessions. It provides the durable scheduled-message records and relational constraints the CRM needs at this scale. Redis, separate read databases, Horizon, and event sourcing are not part of this design.

## Centralized Ansofra use cases

Commands and queries are grouped centrally by business domain and expose direct `execute()` methods. A bus, separate handler layer, or domain-first duplicate controller/repository architecture would duplicate Laravel's native infrastructure and is not needed.

## One configured barbershop

The current deployment supports one shop. Existing business keys and membership records remain useful for referential integrity and authorization; they are not SaaS tenancy or workspace switching. Historical appointment/availability migration definitions remain pending inspection of the configured PostgreSQL database and must not be deleted or edited in place.

## Official WhatsApp Cloud API

Only the official Meta provider interface is used. Template approval, business verification, credentials, webhook reachability, and delivery are external prerequisites and are not represented as complete without evidence.

## App rollback and schema

Deployment rollback restores previous stable application and web images, leaving data volumes and migrations unchanged. Forward migrations must remain compatible with that previous release. Database rollback or restore is a separate reviewed recovery procedure backed by a verified backup.
