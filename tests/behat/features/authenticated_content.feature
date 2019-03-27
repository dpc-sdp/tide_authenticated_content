@skipped
# @TODO remove @skipped once the module is extracted to its own repo.
Feature: Authenticated Content

  Content Approver (role: approver) Owner (role: editor) or Administrator I can restrict access to specific content so that the general public can not read things for government

  @api
  Scenario Outline: Approvers, Editos and Administrators can create Authenticated Content Terms
    Given I am logged in as a user with the "<role>" role
    When I go to "/admin/structure/taxonomy/manage/authenticated_content/add"
    Then I should get a "<response>" HTTP response
    And save screenshot
    Examples:
      | role               | response |
      | authenticated user | 404      |
      | administrator      | 200      |
      | editor             | 200      |
      | approver           | 200      |
      | previewer          | 404      |
      | grant_author       | 404      |

  @api
  Scenario: Create an authenticated landing page
    Given I am logged in as a user with the "editor" role
    Given "authenticated_content" terms:
      | name           | description          | format     | language |
      | Demo Restrict   | restrict              | plain_text | en       |
    When I go to "/node/add/landing_page"
    Then I see field "Restricted Content"
    And I should see an "input#edit-field-authenticated-content-0-target-id" element

  @api
  Scenario: Authenticated Page show login form
    Given "authenticated_content" terms:
      | name           | description          | format     | language |
      | Demo Protect   | protect              | plain_text | en       |
    Given "landing_page" content:
      | title                    | url            | site_id | primary_site | authenticated_content |
      | Page is Restricted        | page-restricted |       4 |            4 | Demo Protect      |
    Given I am an anonymous user
    When I go to "/page-restricted"
    Then I should get a "401" HTTP response
    And I should see an "input#username" element
    And save screenshot

  @api
  Scenario: Authenticated Page show login form
    Given "authenticated_content" terms:
      | name           | description          | format     | language |
      | Demo Restrict   | restrict              | plain_text | en       |
    Given "landing_page" content:
      | title                    | url            | site_id | primary_site | authenticated_content |
      | Page is Restricted        | page-restricted |       4 |            4 | Demo Restrict      |
    Given I am an anonymous user
    When I go to "/page-restricted"
    Then I should get a "401" HTTP response
    And I should see an "input#username" element
    And save screenshot

