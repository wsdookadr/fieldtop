Intro
-----

Fieldtop looks at all fields in all tables and all databases
and analyzes how close the values in each field are to the maximum
(or minimum) allowed value for the given field type. This can give you
interesting insight into your databases. A typical output looks like
this:

| Field                 | Min           | Max   |
| --------------------- |:-------------:| -----:|
| community.users.id    |               |   30% |
| community.users.name  |               |  100% |
| community.users.karma |         60%   |    1% |
| community.posts.id    |               |    1% |
| community.posts.text  |               |  100% |

This would tell you a couple of things at a glance:
* Your user IDs use 30% of the available range. Depending on how long the project has been running, this can be ok or could mean your field is in danger of overflowing at some point.
* Some user names already use the maximum length. This is probably ok if your database design is in sync with your application.
* The negative values in the user karma column are already pretty big. Maybe you did not expect that people aquire negative karma so fast?

Use-case
----------------

On databases that are in-place and being used for extended
periods of time, a lot of data is being accumulated.
This means that at some point, some of the data will outgrow
the designated data types. This tool provides a way of handling
this problem in a proactive/preventive manner.

This tool can offer useful information for integer data types (for
example ID columns, or for data types that grow in size over time)
and for text data types.

The following data or data types are outside the scope of this tool:
- [BIT fields](https://dev.mysql.com/doc/refman/5.7/en/bit-field-literals.html)
- columns storing [UUID values](http://dev.mysql.com/doc/refman/5.7/en/miscellaneous-functions.html#function_uuid)
- columns that store IP addresses
- columns that store hashes values (MD5, SHA1 for example)

Testing
-------

This tool has been developed and tested using MySQL 5.5.49 and PHP 5.5.9
and is expected to work on PHP >= 5.5.9

Contributing
------------

You are welcome to send [pull requests](https://github.com/wsdookadr/fieldtop/pulls)
with enhancements or fixes to this project.

Bugs
----

Please report any bugs in [the issue tracker](https://github.com/wsdookadr/fieldtop/issues/new).
