## Invoice Structure:

The invoice should contain the following fields:
* **Invoice ID**: Auto-generated during creation.
* **Invoice Status**: Possible states include `draft,` `sending,` and `sent-to-client`.
* **Customer Name** 
* **Customer Email** 
* **Invoice Product Lines**, each with:
  * **Product Name**
  * **Quantity**: Integer, must be positive. 
  * **Unit Price**: Integer, must be positive.
  * **Total Unit Price**: Calculated as Quantity x Unit Price. 
* **Total Price**: Sum of all Total Unit Prices.

## Required Endpoints:

1. **View Invoice**: Retrieve invoice data in the format above.
2. **Create Invoice**: Initialize a new invoice.
3. **Send Invoice**: Handle the sending of an invoice.

## Functional Requirements:

### Invoice Criteria:

* An invoice can only be created in `draft` status. 
* An invoice can be created with empty product lines. 
* An invoice can only be sent if it is in `draft` status. 
* An invoice can only be marked as `sent-to-client` if its current status is `sending`. 
* To be sent, an invoice must contain product lines with both quantity and unit price as positive integers greater than **zero**.

### Invoice Sending Workflow:

* **Send an email notification** to the customer using the `NotificationFacade`. 
  * The email's subject and message may be hardcoded or customized as needed. 
  * Change the **Invoice Status** to `sending` after sending the notification.

### Delivery:

* Upon successful delivery by the Dummy notification provider:
  * The **Notification Module** triggers a `ResourceDeliveredEvent` via webhook.
  * The **Invoice Module** listens for and captures this event.
  * The **Invoice Status** is updated from `sending` to `sent-to-client`.
  * **Note**: This transition requires that the invoice is currently in the `sending` status.

## Technical Requirements:

* **Preferred Approach**: Domain-Driven Design (DDD) is preferred for this project. If you have experience with DDD, please feel free to apply this methodology. However, if you are more comfortable with another approach, you may choose an alternative structure.
* **Alternative Submission**: If you have a different, comparable project or task that showcases your skills, you may submit that instead of creating this task.
* **Tests**: Core invoice logic must be covered by tests. Choose the testing strategy that best demonstrates the correctness of your solution.
* **Documentation**: Candidates are encouraged to document their decisions and reasoning in comments or a README file, explaining why specific implementations or structures were chosen.

## Evaluation Criteria

We look at the overall shape of the solution, not a checklist. In particular:
* Architecture, separation of concerns, and clarity of module boundaries.
* Testing strategy — what you chose to test and why.
* How the send/deliver workflow behaves when things do not go as expected.

## Note on the Notification Module:

The Notification module included in this repository is a minimal, mock integration example. It is intentionally simple and should not be treated as a reference for DDD structure or for the expected invoice design.

## Setup Instructions:

* Start the project by running `./start.sh`.
* To access the container environment, use: `docker compose exec app bash`.

## Design notes

Send commits `sending` together with a pending outbox row, then calls `NotificationFacade` in the same request. The webhook is a separate request and may arrive during `notify`. The invoice must already be `sending`, or the listener ignores the event. That is why status changes before notify, not after.

Status changes use a conditional update (`WHERE status = expected`). SQLite is configured with WAL and a 5s busy timeout. A failed notification reverts to `draft` only while the row is still `sending`. If a concurrent delivery already moved the row, compensation leaves that status in place.

Repeating `POST /send` while the invoice is `sending` does not send a second email when the outbox is already `notified`. Send after `sent-to-client` returns 422.

A crash after the outbox commit and before `notify` leaves a `pending` row. `php artisan invoices:dispatch-pending-notifications` calls the same delivery path without a queue worker. A failed `notify` marks the outbox `failed` only after reverting to `draft`. If the webhook already set `sent-to-client`, the outbox becomes `notified`. Dummy delivery is at-least-once: a crash during `notify`, a driver that sends mail and then throws, or a stale claim taken over after a successful `notify` can produce a second email. A lost send race that finds the invoice back in `draft` retries instead of returning 422. Revert to `draft` is one update that requires the caller’s processing claim token. The scheduler is not started by Docker; run the artisan command or repeat `POST /send`.
