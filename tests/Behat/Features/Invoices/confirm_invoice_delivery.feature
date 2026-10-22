Feature: Confirm invoice delivery
  In order to know that a customer has received their invoice
  As a user of the invoicing module
  I need the notification provider's delivery confirmation to mark the invoice as sent to the client

  Rules:
  - Only an invoice that is being sent (status "sending") can have its delivery confirmed
  - Confirming the delivery changes the invoice status to "sent-to-client"
  - An invoice whose delivery cannot be confirmed keeps its status
  - The provider confirms deliveries through a webhook, which acknowledges every confirmation, even one
    that cannot be applied, so the provider does not keep retrying it

  The use_case suite calls the use case, which reports why a confirmation cannot be applied. The api suite
  goes through the webhook, which only acknowledges it; those scenarios are tagged for one suite only.

  # Happy paths

  Scenario: Confirming the delivery of an invoice being sent
    Given an invoice in status "sending" for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    When the notification provider confirms the delivery of the invoice
    Then the invoice status should be "sent-to-client"

  Scenario: Confirming the delivery of an invoice that has just been sent
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    And I have sent the invoice
    When the notification provider confirms the delivery of the invoice
    Then the invoice status should be "sent-to-client"
    And the invoice total price should be 300

  # Sad paths: invoice status

  @use_case_only
  Scenario Outline: The delivery of an invoice that is not being sent cannot be confirmed
    Given an invoice in status "<status>" for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    When the notification provider confirms the delivery of the invoice
    Then I should be told that the delivery cannot be confirmed because the invoice is not being sent
    And the invoice status should be "<status>"

    Examples:
      | status         |
      | draft          |
      | sent-to-client |

  @api_only
  Scenario Outline: A delivery confirmation for an invoice that is not being sent is acknowledged but ignored
    Given an invoice in status "<status>" for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    When the notification provider confirms the delivery of the invoice
    Then the delivery confirmation should be acknowledged
    And the invoice status should be "<status>"

    Examples:
      | status         |
      | draft          |
      | sent-to-client |

  @use_case_only
  Scenario: The delivery of an invoice cannot be confirmed twice
    Given an invoice in status "sending" for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    And the notification provider has confirmed the delivery of the invoice
    When the notification provider confirms the delivery of the invoice
    Then I should be told that the delivery cannot be confirmed because the invoice is not being sent
    And the invoice status should be "sent-to-client"

  @api_only
  Scenario: A repeated delivery confirmation is acknowledged but ignored
    Given an invoice in status "sending" for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    And the notification provider has confirmed the delivery of the invoice
    When the notification provider confirms the delivery of the invoice
    Then the delivery confirmation should be acknowledged
    And the invoice status should be "sent-to-client"

  # Sad paths: everything else

  @use_case_only
  Scenario: The delivery of an invoice that does not exist cannot be confirmed
    When the notification provider confirms the delivery of invoice "5f0c8a4e-3b9d-4e2a-9c61-7d2f4b8e1a03"
    Then I should be told that invoice "5f0c8a4e-3b9d-4e2a-9c61-7d2f4b8e1a03" does not exist

  @api_only
  Scenario: A delivery confirmation for something that is not an invoice is acknowledged but ignored
    When the notification provider confirms the delivery of invoice "5f0c8a4e-3b9d-4e2a-9c61-7d2f4b8e1a03"
    Then the delivery confirmation should be acknowledged
    And there should be 0 invoices
