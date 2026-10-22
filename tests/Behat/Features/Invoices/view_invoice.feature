Feature: View invoice
  In order to check what a customer is being billed for
  As a user of the invoicing module
  I need to view an invoice with its product lines and totals

  # Happy paths

  Scenario: Viewing an invoice with product lines
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Mouse        | 3        | 10         |
      | Keyboard     | 2        | 150        |
      | Cable        | 1        | 1          |
    When I view the invoice
    Then I should see the invoice's ID
    And the invoice status should be "draft"
    And the invoice customer name should be "Jane Doe"
    And the invoice customer email should be "jane.doe@example.com"
    And the invoice should have the following product lines:
      | product name | quantity | unit price | total unit price |
      | Mouse        | 3        | 10         | 30               |
      | Keyboard     | 2        | 150        | 300              |
      | Cable        | 1        | 1          | 1                |
    And the invoice total price should be 331

  Scenario: Viewing an invoice without product lines
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" without product lines
    When I view the invoice
    Then the invoice status should be "draft"
    And the invoice should have no product lines
    And the invoice total price should be 0

  Scenario Outline: Viewing an invoice shows its current status
    Given an invoice in status "<status>" for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    When I view the invoice
    Then the invoice status should be "<status>"

    Examples:
      | status         |
      | draft          |
      | sending        |
      | sent-to-client |

  Scenario: Viewing an invoice after sending it
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    And I have sent the invoice
    When I view the invoice
    Then the invoice status should be "sending"
    And the invoice total price should be 300

  Scenario: Viewing one invoice does not show another
    Given a draft invoice for customer "Jane Doe" with email "jane.doe@example.com" and product lines:
      | product name | quantity | unit price |
      | Keyboard     | 2        | 150        |
    And a draft invoice for customer "John Smith" with email "john.smith@example.com" and product lines:
      | product name | quantity | unit price |
      | Mouse        | 3        | 10         |
    When I view the invoice for customer "John Smith"
    Then the invoice customer name should be "John Smith"
    And the invoice should have the following product lines:
      | product name | quantity | unit price | total unit price |
      | Mouse        | 3        | 10         | 30               |
    And the invoice total price should be 30

  # Sad path

  Scenario: An invoice that does not exist cannot be viewed
    When I view the invoice "5f0c8a4e-3b9d-4e2a-9c61-7d2f4b8e1a03"
    Then I should be told that invoice "5f0c8a4e-3b9d-4e2a-9c61-7d2f4b8e1a03" does not exist
