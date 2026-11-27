# Staff roles and authorization

The CRM serves one configured barbershop. Authorization is based on an active
membership in that barbershop and the permissions of the associated role. A
business identifier supplied by a request is not proof of access. The
`ResolveBarbershop` middleware requires exactly one active barbershop and an
active user membership before protected application routes proceed.

## Enforcement model

- Stable permission identifiers and privileged permission groups are
  centralized in the authorization configuration and exposed through the
  authorization config object.
- Default roles and their permission lists are seeded by
  `BusinessRoleSeeder`. The owner role is reserved and has wildcard authority;
  its role cannot be granted in an invitation or ordinary role assignment.
  Custom roles cannot create or rename themselves to the reserved `owner`
  slug.
- Staff can only delegate permissions they themselves hold. Role and staff
  commands also enforce authorization, so calling a CQRS use case directly
  does not bypass the permission check. The frontend's hidden controls are only
  presentation; backend checks are authoritative.
- Routes and controllers use authentication, Form Requests, policies and
  permission checks. Sensitive mutation commands re-check authorization.
  Customer, consent, visit, business-settings and WhatsApp command-level
  authorization is covered by direct invocation tests.
- The owner role is protected against lower-privileged staff changes. Staff
  invitations, role assignments, suspension and other selected security
  actions are audited. Audit records are not ordinary editable staff data.

## Built-in role intent

The canonical permission arrays are in `database/seeders/BusinessRoleSeeder.php`.
In summary:

| Role | Intended access |
| --- | --- |
| Owner | Full business authority. Owner access is not a SaaS/platform administrator. |
| Manager | Broad customer, visit, service, staff, conversation, automation, template, review, analytics and read access. Privileged capabilities still depend on granted permissions and MFA. |
| Receptionist | Customer intake and updates, consent, visits, conversations/replies and review recording. |
| Barber | Customer lookup/notes, visit recording and service catalog viewing. |
| Viewer | Read-only dashboard, customer, visit, analytics and review access. |

Custom roles may be created from an authorized permission catalogue. A role
editor must not offer permissions the acting user cannot delegate. Review the
current seeder and permission configuration when assigning responsibilities;
the role names alone do not grant access.

## Privileged MFA

The configured privileged permission list/groups are in
`config/authorization.php`. Owners and users with these permissions must enroll
and confirm Fortify two-factor authentication to use protected application
routes. Update that configuration when a new permission grants a security-
sensitive capability.

## Adding or changing a protected feature

For each route and use case:

1. Define or reuse a centrally named permission; avoid role-name checks in
   business logic.
2. Apply authentication and the appropriate route/controller policy.
3. Enforce the permission inside any state-changing CQRS command that can be
   invoked outside the HTTP controller. A query returning sensitive data must
   likewise be scoped/authorized at its entry point.
4. Scope repository access to the configured business and validate referenced
   records belong to it.
5. Add tests for authorized and unauthorized direct command/query calls,
   cross-business IDs, role delegation boundaries, and the relevant route.
6. Audit privileged access changes without recording credentials or
   unnecessary customer content.

## Verification and limits

`tests/Feature/Staff/StaffAccessControlTest.php`, staff lifecycle/invitation
tests, command authorization tests, customer profile tests, and the Playwright
role-management flows cover representative policy, role-delegation,
owner-protection, direct-command and interface behavior. These tests are not a
complete formal proof that every data path is authorized. Review new queries,
exports, jobs, webhooks and commands for equivalent scoping whenever they are
added. The configured production database and staff dataset have not been
inspected here.
