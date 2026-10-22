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

----------------------

# The Solution

The structure I chose for my code is what I believe to be a fairly typical DDD one. Although I have never worked on a purely DDD project, I have always been a big fan of that approach and I like to use it wherever possible.

Another approach that I have always been fond of is the use-case centered code, AKA Screaming Architecture. I like for the business logic to live in their own, dedicated classes that are crystal clear about their intent, and I also like them to be independent of any framework.

## Toolkit

I barely wrote any code by hand - I gave that job to Claude Code (Pro subscription). Obviously, however, I didn't just throw the README at it; instead, I gave it strict instructions about the way I want to have my code structured.

* PHPUnit: for unit tests and functional tests of contracts defined by the domain.
* Behat: for acceptance tests. These are implemented in two layers: API and Use Case. This approach allows to choose between running the faster, behavior-oriented suite that skips the delivery mechanism, or the slower, but more thorough, end-to-end kind of suite that makes sure that the application works as a whole. In a large, complex system this might make a significant difference in the duration of the build. Here, it's a kind of POC. 
* Deptrac: for making sure that the layers stay clean, and that separate domains stay agnostic of one another.

## Separation of Concerns

As I said earlier, I like the framework-agnostic approach, which means that no code inside Domain or Use Case layer depends on Laravel. This helps with testing the validity of the business rules independent of any framework. 

As for the domains, it seemed natural to me that Invoices are not concerned about Notifications. While we want to send invoices to the customer, making the Invoice domain know about the Notifications module would couple it to the specifics of the latter's API, which exposes details like "subject" and "message". Instead, I decided to use a port in the Send Invoices use case. Right now its implementation belongs to the Invoices module for simplicity; in a more complex application, there could be a whole separate module that decides what kind of notifications are sent which way and using what wording.

This is the first time I've ever written any code in Laravel - a big Symfony fanboy, I am. For this reason, I wasn't able to check Claude's results for best practices in this framework. I also wasn't sure about the way in which `EloquentInvoiceRepository` is implemented. Perhaps an Eloquent model class could implement an `Invoice` interface defined in the domain. This would probably make the tracking of the changes easier than a manual rewrite, especially for a larger and a more complex aggregate.

## Testing Strategy

First, I had Claude generate Behat scenarios for the required use cases. This way I had a detailed specs from the beginning, making sure that all edge cases are covered. I continued from the outside in: the first production classes that I wrote were the Use Cases. They came with their own unit tests. By writing them first, I was able to easily make decisions about what to implement in place, and what to defer to other classes. Then, each class that was created as a result of that came with its own unit test. I also made functional tests for classes that rely on the framework or the database for their correctness, like the validator or the repository.

## Room for Improvement

* The unit tests' readability.
* The organization of Behat helper classes, and splitting those pretty large context classes into smaller ones.
* Using Value Objects for things like Email, and _definitely_ whenever money is involved. I kept the Product Line amount as an integer for simplicity, but in a real production application I would very strongly opine for using a VO there, not least because any amount of money must store its currency.

## Things I Would Have Asked About

* `sent-to-client` vs. `delivered`. The webhook uses the latter term, while the invoice status changes to `sent`, which is inconsistent in my opinion. I would like to clarify that with the domain expert.
* `ResourceDeliveredEvent` (README) vs. `WebhookDeliveredEvent` (code) - which name is correct.
