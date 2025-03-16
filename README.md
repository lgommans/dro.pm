# [dro.pm](http://dro.pm) - share files, links, text

The project now contains a web and android part. This README is mostly about the web part.

## The website

This project's code is old and ugly but I uploaded it anyway because people were interested.

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

GPLv3: see the `./LICENSE` file.

**Security**

Because users can create what looks like files on your domain, they can also do website verification
and claim your site at third parties. To avoid this, you should identify which third parties are
relevant to you and block illegitimate verification attempts. In my case, this was done by
configuring the web server to block user agents containing 'site-verification' (case-insensitive).

You might also want to create a `favicon.ico` and `robots.txt`.

**To do**

There are many ideas I have for this project, but most are hard to place in the UI. The main feature
I still want to integrate, is making longer links valid for a custom time period. The original idea
was that validity could go up to forever with 7 characters or more, but I'm no longer sure that's a
good idea. The main feature of this site is expiring old garbage and there are plenty of sites where
you can generate life-long and custom URLs on a FCFS basis. The time limits would probably be
something like between 2 seconds and 2 weeks.

Another idea includes a manager where you have one link behind which is an overview of links,
texts (pastes), and files, and people opening the link can pick from the menu what they want to open.
An example case for this would be a classroom setting, where the teacher generates a link the first
time s/he needs it and adds more resources to it throughout the lesson. Here, it would also be useful to give a
store/load option, where the collective page can be downloaded. And, after downloading, maybe also
load it back into a new link later, for example for the next session.

An API would also be neat. There sort-of already is one, but I'd like to formalize it and to make it
better.

The Android app does not allow sharing text or URLs, and there should be a 'remove' button. It could
have an overview of currently valid links and allow you to remove those. And it does not allow custom
links yet.

It would also be cool to have better command line support, where the program remembers your last
tokens and you don't have to copy/paste them from previous output. And upload progress.

Finally, I would like to transfer arbitrarily large files peer to peer. WebRTC or TCP hole punching,
something fancy will have to be done for this, but people could transfer files without them having to
be stored on the server, even encrypted. The drawback here is UX: telling people to leave the tab
open and that the URL will expire as soon as they close it (and it becomes unavailable) is not very
user friendly. The text should be clear and concise when this is the case. The user should, for small
files, also be able to use the traditional method. Another UI challenge: how to give the user that
option (it should probably be the default) without making everything more difficult when you do not
want to have to answer ten questions in order to share something?

Feel free to work on any of this. Things like expanding Android app are straightforward, but before
you commit a large chunk of your time to something like UI problems,
be sure to bounce ideas off of me first! I want to keep the product useful for everyone and myself,
and that means applying quality control.
