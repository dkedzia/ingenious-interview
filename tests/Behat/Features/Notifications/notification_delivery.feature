Feature: Notification delivery
  In order to know when a customer has received a notification
  As the application
  I need the notification module to announce confirmed deliveries

  Scenario: The provider confirms delivery of a notification
    Given the application listens for delivered notifications
    When the notification provider confirms delivery of resource "5f0c8a4e-3b9d-4e2a-9c61-7d2f4b8e1a03"
    Then a delivered event should be dispatched for resource "5f0c8a4e-3b9d-4e2a-9c61-7d2f4b8e1a03"
