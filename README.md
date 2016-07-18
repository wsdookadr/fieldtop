Intro
-----

Overflow-Checker looks at all fields in all tables and all databases and
analyzes how close the values in each field are to the maximum allowed
value for the given field type. This can give you interesting insight
into your databases. Which fields might overflow soon or which fields
have very large values for unintended reasons. Numeric values are checked
against the highest allowed number. Text values against the max length
for the field.


Use-case
----------------

On databases that are in-place and being used for extended
periods of time, a lot of data is being accumulated.
This means that at some point, some of the data will outgrow
the designated data types. This tool provides a way of handling
this problem in a proactive/preventive manner.


Testing
-------

This tool has been developed and tested using MySQL 5.5.49 and PHP 5.5.9
and is expected to work on PHP >= 5.5.9
