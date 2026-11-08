# SYSTEM ARCHITECTURE

> Written during SYSTEM_GENERATION. Updated only when architecture decisions change.

---

## Architecture Pattern
[Monolith / Modular Monolith / Microservices / Serverless]

## Layer Map
| Layer      | Technology | Responsibility                    |
|------------|------------|-----------------------------------|
| Frontend   |            |                                   |
| Backend    |            |                                   |
| Database   |            |                                   |
| Cache      |            |                                   |
| Queue      |            | (add only if needed)              |
| Auth       |            |                                   |

## Data Flow
[Describe how a request moves through the system — user → frontend → API → DB → response]

## External Integrations
[Third-party APIs, webhooks, payment providers, email services — or "none"]

## Scaling Strategy
[How the system grows — horizontal scaling / caching / CDN / read replicas / queue offload]

## Known Risks
[Performance bottlenecks, single points of failure, or areas to watch as scale increases]

## Architecture Decisions
See: `decisions/decision_log.md`
