@mod @mod_workbook
Feature: Workbook submission and assessment
  In order to use workbook activity
  As a student
  I need to be able to view workbook content and submit answers to questions

  Background:
    Given I am on a totara site
    And the following "users" exist:
      | username | firstname    | lastname | email            |
      | student1 | Eugene1      | Student1 | student1@asd.com |
      | student2 | Eugene2      | Student2 | student2@asd.com |
      | student3 | Eugene3      | Student3 | student3@asd.com |
      | student4 | Eugene4      | Student4 | student3@asd.com |
      | teacher1 | Elmaret1     | Teacher1 | teacher1@asd.com |
      | teacher2 | Elmaret2     | Teacher2 | teacher2@asd.com |
      | teacher3 | Elmaret3     | Teacher3 | teacher3@asd.com |
    And the following "courses" exist:
      | fullname  | shortname |
      | Course 1   | c1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | c1     | student        |
      | student2 | c1     | student        |
      | student3 | c1     | student        |
      | student4 | c1     | student        |
      | teacher1 | c1     | editingteacher |
      | teacher2 | c1     | editingteacher |
      | teacher3 | c1     | editingteacher |
    And the following "groups" exist:
      | name | course   | idnumber |
      | group1 | c1       | group1 |
      | group2 | c1       | group2 |
    And the following "group members" exist:
      | user        | group   |
      | student1    | group1  |
      | student2    | group1  |
      | student3    | group2  |
      | student4    | group2  |
      | teacher1    | group1  |
      | teacher2    | group2  |

  @javascript
  Scenario: Configure workbook
    Given I log in as "admin"
    And I follow "Course 1"
    And I turn editing mode on
    # Add workbook
    And I add a "Workbook" to section "1" and I fill the form with:
      | Workbook name  | Separate groups workbook       |
      | Group mode  | Separate groups                   |
    And I follow "Separate groups workbook"
    And I follow "Edit pages"
    # Add a page
    And I press "Add a page"
    And I set the following fields to these values:
      | Title | The Long Page 1 |
      | Navigation title | Page 1 navtitle    |
    And I press "Save changes"
    Then I should see "The Long Page 1"
    # Add static page content item
    Given I click on "Add page item" "link" in the ".config-mod-workbook-page[pageid=1]" "css_element"
    And I set the following fields to these values:
      | Item name       | Page item 1                                           |
      | Content         | This a static page item type for some static content  |
      | Allow comments  | 1                                                     |
    Then the "Required grade" "field" should be disabled
    Given I press "Save changes"
    Then I should see "This a static page item type for some static content"
    # Add another page with a longtext question
    Given I press "Add a page"
    And I set the following fields to these values:
      | Title | The Long Page 2 |
      | Navigation title | Page 2 navtitle    |
    And I press "Save changes"
    Then I should see "The Long Page 2"
    Given I click on "Add page item" "link" in the ".config-mod-workbook-page[pageid=2]" "css_element"
    And I set the following fields to these values:
      | Type            | Essay question                    |
      | Item name       | Page item 2                       |
      | Content         | This the first question page item |
      | Required grade  | 100                               |
    And I press "Save changes"
    Then I should see "This the first question page item"
    # Add a subpage with a longtext question
    Given I press "Add a page"
    And I set the following fields to these values:
      | Title               | The Long SubPage 3 |
      | Navigation title    | Page 3 subnavtitle |
      | Parent page         | The Long Page 2    |
    And I press "Save changes"
    Then I should see "The Long SubPage 3"
    Given I click on "Add page item" "link" in the ".config-mod-workbook-page[pageid=3]" "css_element"
    And I set the following fields to these values:
      | Type            | Essay question                        |
      | Item name       | Page item 3                           |
      | Content         | This the second question page item    |
      | Required grade  | 50                                    |
    And I press "Save changes"
    Then I should see "This the second question page item"


  @javascript
  Scenario: Submit and assess workbook
    Given I log in as "admin"
    And I follow "Course 1"
    And I turn editing mode on
    # Add workbook
    And I add a "Workbook" to section "1" and I fill the form with:
      | Workbook name  | Separate groups workbook       |
      | Group mode  | Separate groups                   |
    And I follow "Separate groups workbook"
    And I follow "Edit pages"
    # Add a page
    And I press "Add a page"
    And I set the following fields to these values:
      | Title | The Long Page 1 |
      | Navigation title | Page 1 navtitle    |
    And I press "Save changes"
    # Add static page content item
    And I click on "Add page item" "link" in the ".config-mod-workbook-page[pageid=1]" "css_element"
    And I set the following fields to these values:
      | Item name       | Page item 1                                           |
      | Content         | This a static page item type for some static content  |
      | Allow comments  | 1                                                     |
    And I press "Save changes"
    # Add another page with a longtext question
    And I press "Add a page"
    And I set the following fields to these values:
      | Title | The Long Page 2 |
      | Navigation title | Page 2 navtitle    |
    And I press "Save changes"
    And I click on "Add page item" "link" in the ".config-mod-workbook-page[pageid=2]" "css_element"
    And I set the following fields to these values:
      | Type            | Essay question                    |
      | Item name       | Page item 2                       |
      | Content         | This the first question page item |
      | Required grade  | 100                               |
    And I press "Save changes"
    # Add a subpage with a longtext question
    And I press "Add a page"
    And I set the following fields to these values:
      | Title               | The Long SubPage 3 |
      | Navigation title    | Page 3 subnavtitle |
      | Parent page         | The Long Page 2    |
    And I press "Save changes"
    And I click on "Add page item" "link" in the ".config-mod-workbook-page[pageid=3]" "css_element"
    And I set the following fields to these values:
      | Type            | Essay question                        |
      | Item name       | Page item 3                           |
      | Content         | This the second question page item    |
      | Required grade  | 50                                    |
    And I press "Save changes"
    And I log out

    # Submit the first question as student1
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "Separate groups workbook"

    # Check first page
    When I follow "Separate groups workbook"
    Then I should see "Page 1 navtitle"
    And I should see "Page 2 navtitle"
    And I should see "Page 3 subnavtitle"
    And I should see "The Long Page 1"
    And I should see "This a static page item type for some static content"
    And I should see "Comments"

    # Check navigation
    When I click on ".workbook-nav-pages div[pageid=2]" "css_element"
    And I set the field "workbook-essay-response" to "Eugene is pretty boss"
    # Check bottom navigation
    And I click on ".mod-workbook-nav-next" "css_element"
    Then I should see "The Long SubPage 3"

    # Check that instant save worked
    When I click on ".mod-workbook-nav-prev" "css_element"
    Then I should see "Eugene is pretty boss"
    And I should see "Modified on"
    And I should see "Status: Draft" in the ".mod-workbook-item-sitrep" "css_element"

    # Now submit for assessment
    When I click on "Submit for assessment" "button" confirming the dialogue
    Then I should see "Status: Submitted" in the ".mod-workbook-item-sitrep" "css_element"
    And I should see "Not graded" in the ".mod-workbook-item-sitrep" "css_element"
    And I log out

    # Submit the last question as student3 (different group)
    When I log in as "student3"
    And I follow "Course 1"
    And I follow "Separate groups workbook"
    And I click on ".mod-workbook-nav-next" "css_element"
    And I click on ".mod-workbook-nav-next" "css_element"
    And I set the field "workbook-essay-response" to "Harties is in the valley!"
    And I click on "Submit for assessment" "button" confirming the dialogue
    Then I should see "Status: Submitted" in the ".mod-workbook-item-sitrep" "css_element"
    And I log out

    # Log in as teacher1 and assess
    When I log in as "teacher1"
    And I run the scheduled task "\mod_workbook\task\send_submission_notifications"

    # Check notifications
    And I click on "Dashboard" in the totara menu
    Then I should see "Essay question submitted by Eugene1"
    And I should not see "Essay question submitted by Eugene3"

    # Check submission
    When I follow "Home"
    And I follow "Course 1"
    And I follow "Separate groups workbook"
    And I follow "Assess submissions"
    And I press "Clear"
    Then I should see "Eugene1 Student1"
    And I should see "Page item 2"
    And I should not see "Eugene2 Student2"
    When I click on "Assess" "link" in the "table.reportbuilder-table" "css_element"
    Then I should see "Eugene1 Student1"
    And I should see "Status: Submitted" in the ".mod-workbook-item-sitrep" "css_element"
    # Not meeting grade
    When I wait until ".mod-workbook-submission-grade input[name=workbook-submission-grade-input]" "css_element" exists
    And I set the field "workbook-submission-grade-input" to "7"
    And I click on ".mod-workbook-nav-next" "css_element"
    And I click on ".mod-workbook-nav-prev" "css_element"
    Then I should see "Status: Graded" in the ".mod-workbook-item-sitrep" "css_element"
    When I wait until ".mod-workbook-submission-grade input[name=workbook-submission-grade-input]" "css_element" exists
    And I set the field "workbook-submission-grade-input" to "777"
    And I click on ".mod-workbook-nav-next" "css_element"
    And I click on ".mod-workbook-nav-prev" "css_element"
    Then I should see "Status: Finished"
    And I should see "by Elmaret1"
    # Finished submissions should not be gradable any more.
    And ".mod-workbook-submission-grade input" "css_element" should not exist
    # Ensure no grading is possible for unfinished items:
    # student1 has not completed the second question
    When I click on ".mod-workbook-nav-next" "css_element"
    Then ".mod-workbook-submission-grade input" "css_element" should not exist
    And I log out

    # Finally, log in as student1 and check notification
    When I log in as "student1"
    And I run the scheduled task "\mod_workbook\task\send_graded_notifications"
    # Check notifications
    And I click on "Dashboard" in the totara menu
    Then I should see "Your Essay question submission has been assessed"
    And I log out

    # Teacher in no groups should be seeing all submissions
    When I log in as "teacher3"
    And I follow "Course 1"
    And I follow "Separate groups workbook"
    And I follow "Assess submissions"
    And I press "Clear"
    Then I should see "Eugene1 Student1"
    And I should see "Page item 2"
    And I should see "Eugene3 Student3"
    And I should see "Page item 3"
    And I log out

    # todo: comment notifications?
    # todo: file submissions
