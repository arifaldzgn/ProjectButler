# Project Butler

Project Butler is a personal AI assistant with a Telegram-first UX, extended to support iPhone Shortcuts, Android, web, and desktop clients via a secure REST API. It acts as an intelligent companion for financial tracking, nutrition logging, habit building, and general assistance.

## Architecture

Project Butler uses a centralized routing architecture where all messages—whether from Telegram, iOS Shortcuts, Android, or the Web—are processed through a unified `MessageRouter`.

- **Device-Centric Authentication:** Clients authenticate via per-device Sanctum tokens using a self-service pairing flow (no server-side admin required).
- **Idempotency Support:** Robust retry handling to prevent duplicate records on flaky mobile connections.
- **Queue System:** High-priority queue for immediate message parsing and responses; low-priority queue for behavioral memory learning, analytics, and notification dispatch.
- **Behavioral Memory (Soul Engine):** Learns user habits (e.g., typical calorie counts, recurring bills) over time based on confidence thresholds and implicit/explicit user confirmation.
- **Smart Fallback Engine:** Handles unrecognized commands using a deterministic suggestion engine before falling back to LLM-driven categorization.

For full architectural details, see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Features

- **Financial Management:**
  - Multi-account tracking (Bank, E-wallet, Cash, Credit Card).
  - Expense and income logging via natural language and receipt photo scanning.
  - Sinking funds and savings goals with automated milestone notifications.
  - Dashboard with financial health scoring, debt avalanche strategy planning, and cashflow charts.
- **Health & Nutrition:**
  - Calorie and macro (protein/carbs/fat) tracking based on natural language input.
  - Tailored goal modes (Bulking / Maintenance / Cutting).
- **Habits & Tracking:**
  - Behavioral memory that adapts to user routines.
  - Streaks and personalized recurring reminders.
  - Mood logging and daily/weekly AI-generated summary reports.
- **Dashboards & Webview:**
  - Premium mobile-responsive dashboard (Light/Dark mode) accessible via signed URLs from Telegram.
  - Visual insights and data export capabilities.

## Recent Updates

Project Butler has recently transitioned to **Architecture v2**, which introduces:
- Multi-client support via Sanctum-based device pairing.
- Idempotency middleware and structured intent support.
- Deep integration of AI for parsing multi-modal inputs (e.g., receipt photos).
- Rich administrative logging for unrecognized messages and API token usage.

For a full history of changes, see [docs/CHANGELOG.md](docs/CHANGELOG.md).
