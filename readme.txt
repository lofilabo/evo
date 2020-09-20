INTRODUCTION.

This project solves the problem as communicated by Aran to Richard on Sept 17th 2020.

I like writing documentation, but people don't seem to like reading it, so I'll keep it brief here.

The solution is laid out as follows:

start.php - the entry point of the project
end.php - the terminal point of the project
allclasses.php - the guts of the program; the single-file class library which can be attached to your own project
Y_CONFIG / database.php - the simple config file.  Please edit this to reflect your own database.

PRECONDITIONS;

As per the spec, the testing user is expected to have a database set up with the required table built into it.
The user will also need PHP 7.x available from a shell.

Navigate into the root director and run something similar to:

php -S 127.0.0.1:6789

In a browser, use the URL:

http://127.0.0.1:6789/start.php.


DESIGN PHILOSOPHY;

As per the spec, this project is laid out and concieved with the following in mind:

1. No framework.  

The main class library is intended to be included into an existing project, and no attempt at
any of the following has been attempted:
i. ORM
ii. Templating
iii. Dependency management


2. Implementing the letter of the spec.

The spec explicit says to produce an HTML form which validates and pushed user data into
a database table.


3. Implmenting the spirit of the spec.

Without it saying so, we may infer that the specification wants us to:
Introspect into the single database table;
Use the layout of the table to derive the form;
Use the data types of the table to infer the objects placed on screen;

The solution being delivered has been developed against the test table described in the schema.
However, it is possible to specify a diffrent table name, and as long as the table does not have any eccentric 
field types and AS LONG AS ALL TABLE FIELDS ARE EXPECTED TO BE POPULATED BY USER INPUT, and as long as THE
TABLE'S PRIMARY KEY IS AN AUTOINCREMENTING INTEGER, a form will be produced which mirrors the table structure*.


4. Solve as many security problems as possible.

Where possible, precautions have been taken against:
Injecting SQL into form fields;
Accepting input to the database from sources other than the HTML form;


DOCUMENTATION;

This is not a large project, and what it does is not diffucult to understand.  However, there are some
subtleties in the implementation which require elucidation.

The main body of code is purposefully laid out in a single-file y-follows-x style.  It should be
possible to read the code and comments in the style of a short novel from top to bottom, and 
for a manager or non-programmer to grasp the baics of what is hapenning.  Most of the documentation 
is provided as Paint-Along-With-Nancy** style comments, since it is believed that this aids understanding
and critique.

IT IS NOT SUGGESTED that this style of commentary is appropriate for commercial projects.  It does, however,
lend itself well to the tutor/student model where the Reviewer is expected to be able to grasp flow
and process without recourse to a flow diagram or class hierachy.


KNOWN ISSUES;

This is not production code and should not be thought of as such.  It is intended to show how a very specific 
problem should be solved.

The most obvious omission is that there is no conveient way to style or change HTML without 
editing the PHP class files (the solution does, however, encapsulate its HTML and SQL in self-contaiened
classes, thus observing MVC).  The specification states that no such styling is required, although it 
should be relatively easy to add.

There is no requirement for subsequent presentation of captured data, which means that approximately
half of all security consideration have not been considered.  The reader is referred to Kevin Smith's
excellent essay here: https://kevinsmith.io/sanitize-your-inputs which elucidates modern approaches
to 'what to do with data' both on the way in and out of the application.

TESTS;

The directory pu contains a couple of unit tests.
Specifically, we want to know:

1. Does out introspection class get the correct table information?
(a couple of checks against type/name in a pre-known location will do it)

2. Can we rely on mySQLi to 

* Specifically, there is no way to explicitly handle date fields.  This should be corrected later.
**Specifically for readers in the land of HTV.

Fix the DB details in Test1 and run:
php phpunit-9.3.phar Test1 --colors=auto
and look out for the green box!
