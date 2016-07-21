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
* The IDs for posts are far from the limit
* The text field for the posts is already maxing out the max lenght. Maybe that is not what you want.

The output can be escpecially interesting for databases that have been used for extended
periods of time. Often at some point, some of the data will outgrow the designated data types.

In fact, the the idea to make this tool came up when [no-gravity](https://github.com/no-gravity) noticed that after 10 years of unsupervised learning, his music recommendation system [Gnoosic](http://www.gnoosic.com) maxed out over 50% of the available range for band popularity. The popularity was stored in a signed integer which can hold number up to 2 billion. And Pink Floyd was already at 1.3 billion.

Testing
-------

FieldTop has been developed and tested using MySQL 5.5.49 and PHP 5.5.9
and is expected to work on PHP >= 5.5.9

Contributing
------------

You are welcome to send [pull requests](https://github.com/wsdookadr/fieldtop/pulls)
with enhancements or fixes to this project.

Bugs
----

Please report any bugs in [the issue tracker](https://github.com/wsdookadr/fieldtop/issues/new).
