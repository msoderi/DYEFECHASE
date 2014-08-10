DIEFECHASE README FILE 
----------------------

What Diefechase is
------------------

Diefechase is a PHP script aimed to automatically discovering RSS, ATOM, and RDF channels in websites.

How Diefechase works
--------------------

The software description provided below applies to starting point software.

You have to provide a text file named cwebsites where you write, one for each line, the URL of websites where Diefechase should look for RSS, ATOM and RDF channels.

The text file must locate in the same folder where the PHP file is located.

The PHP script browses to the URL written in the first line, and follows both internal and external links from the given URL down for up to five levels.

By doing this, every time it discovers a link to a RSS, ATOM or RDF channel, Diefechase stores it in the admin__channels database table.

After having completed the search in the website, Diefechase write down some statistics in the admin__channelsd database table.

At each execution, Diefechase also deletes the first line of text input file, so that executing the script multiple times, you will have all websites searched, and the input text file emptied.

For executing the script multiple times automatically, you could launch the provided shell script if you operate under a UNIX environment, or make an equivalent batch file if you run the PHP script under a Windows environment.

Starting point software is tested under UNIX environment, using a MySql database.

Starting point software is effectively used at now by Synd.it, an Italian project where RSS, ATOM and RDF channels are discovered (Diefechase), and parsed to get news made available to common users via a PHP website. Channel parsing code, and presentation website, are not part of this project.
 