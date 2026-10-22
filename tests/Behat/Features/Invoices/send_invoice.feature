Feature: Send invoice
  In order to get paid for the products a customer ordered
  As a user of the invoicing module
  I need to send a draft invoice to its customer

  Rules:
  - Only a draft invoice can be sent
  - An invoice needs at least one product line to be sent
  - Sending notifies the customer by email, then changes the invoice status to "sending"
  - An invoice that is not sent keeps its status, and its customer is not notified

  # Happy path

  Scenario: Sending a draft invoice
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
      | Mouse        | 3        | 10         |
    When I send the invoice
    Then the invoice status should be "sending"
    And the customer should be notified about the invoice at "jane.doe@example.com"

  # Sad paths: invoice status

  Scenario Outline: An invoice that is not a draft cannot be sent
    Given an invoice in status "<status>" for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    When I send the invoice
    Then I should be told that the invoice cannot be sent because it is not a draft
    And the invoice status should be "<status>"
    And the customer should not be notified

    Examples:
      | status         |
      | sending        |
      | sent-to-client |

  Scenario: An invoice cannot be sent twice
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    And I have sent the invoice
    When I send the invoice
    Then I should be told that the invoice cannot be sent because it is not a draft
    And the invoice status should be "sending"
    And the customer should have been notified once

  # Sad paths: product lines

  Scenario: An invoice without product lines cannot be sent
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" without product lines
    When I send the invoice
    Then I should be told that the invoice cannot be sent because it has no product lines
    And the invoice status should be "draft"
    And the customer should not be notified


  # Sad paths: everything else

  Scenario: An invoice that does not exist cannot be sent
    When I send the invoice "5f0c8a4e-3b9d-4e2a-9c61-7d2f4b8e1a03"
    Then I should be told that invoice "5f0c8a4e-3b9d-4e2a-9c61-7d2f4b8e1a03" does not exist
    And the customer should not be notified

  Scenario: An invoice whose customer cannot be notified stays a draft
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    And the notification provider is unavailable
    When I send the invoice
    Then I should be told that the invoice could not be sent to the customer
    And the invoice status should be "draft"

  Scenario: An invoice whose customer could not be notified can be sent again
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    And the notification provider is unavailable
    And I have tried to send the invoice
    And the notification provider is available again
    When I send the invoice
    Then the invoice status should be "sending"
    And the customer should be notified about the invoice at "jane.doe@example.com"
