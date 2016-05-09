# [Drop 'em](http://dro.pm) - share files, links, text

This project's code is old and ugly but I uploaded it anyway because people are interested.

**File info**

- `res/` contains resources, currently only images.

- `frontpage.htm` is the landing page, which calls methods in `api/` (see `api/.htaccess`).

- `gotoUrl.php` turns a short link into a download, redirect, text display or waiting page.

- `fileman.php` handles file uploads. Should probably be in the `api/` folder actually (todo).

- `jsdemo.htm` is an old file to demo, in concept, how the site should work.

**Setup**

Setup the database from `db.sql` in MySQL or something and place these files in your htdocs (or
docroot or wwwroot). You can configure the database credentials in `api/dbconn.php`.

**Architecture**

The frontpage allocates a URL. From that point onwards, loading that URL will display a loading page.
As soon as you do anything, type a character, paste a link, etc., the loading page will turn into a
text display or redirect. When uploading files, the loading page will turn into a download as soon
as the upload finished.

Your time, currently 12 hours, goes in from the moment that the link was last changed. One could
theoretically keep a link alive indefinitely (another design flaw but one that has not been exploited).

There is no rate limiting or anything of the sort, so it's also possible to exhaust the available
URLs (they go up to 3 characters right now, filtering similar ones). When someone does that I'll have
to implement it I guess - and give out some IP bans.

Finally one may try all URLs, scripted, to see what people are storing. This is another thing I might
wnat to monitor for / rate limit in the future.

**License**

Default Dutch copyright applies with the exception that you may use this code and run it locally,
as long as it is for the sole purpose of contributing back to the project. (Why bother publishing
at all then? Well you are welcome to read the code and learn from it, plus you can contribute your
own features to [dro.pm](http://dro.pm).)

**To do**

There are many ideas I have for this project, but many are either hard to place in the UI or I just
don't have time to build them. The main one is having longer URLs and being able to pick one. The
longer the link, the longer it is allowed to be valid. The idea was that it could go up to infinity
with 7 characters or more, but right now I'm not sure whether that is a good idea. The main feature
of this site is expiring old garbage and there are plenty of sites where you can generate life-long
and custom URLs on a FCFS basis. If there are links valid for a week or more, abuse handling might
have to become much more important, whereas I can pretty much ignore that completely now.

Other ideas include a manager where you can add links, text and files, and people opening the link
can pick what they want to open. An example case for this would be a classroom setting. Here it
would also be useful to give a store/load option, where the collective page can be downloaded and
loaded back into a new link later.

Or a password, since links are guessable. It is completely pointless if you can pick your own URL
already, the whole point is easy transferral without knowing each othr so a link + password is
equivalent to, say, bit.ly, but it's still a much requested feature. I guess since other shorteners
don't have file upload.

And finally, arbitrarily large files transferred peer to peer. WebRTC or TCP hole punching, something
fancy will have to be done for this, but people could transfer files without them having to be
stored on the server, even encrypted. The drawback here is UX: telling people to leave the tab open
and that the URL will expire as soon as they close it (and it becomes unavailable) is not very user
friendly.

