# Roundcube plugin - Smart Autocomplete

Self-learning autocomplete algorithm that optimizes order of suggested recipients
by analysing your previously accepted autocomplete suggestions.

This plugin goes beyond most-used-first autosuggestion. It makes one step further
and correlates selected suggestions with typed-in search string. This enables it to
detect _what you mean_. See example below for explanation.



## Example of what you can teach Roundcube by using this plugin

Say you have two contacts with quite similar names:

- friend no.1 is 'Leslie Rosenberg',
- friend no.2 is 'Leslie Rosemann'.

The first one you know since childhood and you call each other by first names, 'Leslie'.
The second contact is your business consultant and you refer to her usually by surname, 'Rosemann'.

If simple most-used-first-on-list algorithm is used, then when you type in 'les' or 'ros'
the same contact would appear first on the autosuggestion list, order depending on which
is used the most.

With Smart Autocomplete, if you start typing 'les' and select 'Leslie RoseNBERG'
from displayed autocomplete suggestions, and go on and start typing 'ros' and
select 'Leslie RoseMANN' this time, you have just trained RC to display correct
contact as first autocomplete suggestion for both occasions.



## Requirements

Whichever RC release has this PR merged: https://github.com/roundcube/roundcubemail/pull/5203

Expected to be merged in v1.1.6 and v1.2.0.

Until PR above is merged, you can use patches from [patches/](patches/) directory to prepare
your Roundcube instance for use with this plugin.



## TODO

- setting button to remove all learned autocomplete data, so user can start teaching
    RC from scratch at any time
- define internal behaviour when multiple search string hits for the same contact are present:
-- delete longer ones as soon as shorter searchstring accepted_count is larger?
-- ATM they even out eventually. But keeping them pollutes database a little.
