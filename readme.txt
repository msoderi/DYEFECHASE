DYEFECHASE README FILE 
----------------------

What Dyefechase is
------------------

Dyefechase is a PHP script aimed to automatically discovering RSS, ATOM, and RDF channels in websites.

How Dyefechase works
--------------------

You provide an input text file where you write, one for each line, the URL of websites where Dyefechase should look for RSS, ATOM and RDF channels. Path and filename of this input file can be configured by editing the Config section of PHP script.

The PHP script copies the input text file you provided, to a working file, whose path and filename can be configured by editing the Config section of PHP script. The PHP script will use this working file during its work, so leaving the input file you provided unaltered.

The PHP script browses to the URL written at the first line of the working input text file, and follows both internal and external links from the given URL down for up to five levels.

While doing this, every time it discovers a link to a RSS, ATOM or RDF channel, Dyefechase stores it in the database, in a table whose name can be configured by editing the Config section of PHP script.

After having completed the search in the website, Dyefechase write down some statistics in the database, in a table whose name can be configured by editing the Config section of PHP script.

At each execution, Dyefechase also deletes the first line from the working input text file, so that executing the PHP script multiple times, you will have all websites searched, and the working input text file emptied. When the PHP script finds the working input text file empty, or is not able to find the working input text file where expected, it creates it, copying from the original input text file you provided. This way, you can launch your search and forgive the daemon. When it'll finish, it'll automatically restart searching from the first website. 

For executing the PHP script multiple times automatically, you could launch the provided shell script if you operate under a UNIX environment, or make an equivalent batch file if you run the PHP script under a Windows environment.

Starting point software is tested under UNIX environment, using a MySql database. In effect, starting point software is effectively used at now by Synd.it, an Italian project where RSS, ATOM and RDF channels are discovered (Dyefechase), and parsed to get news made available to common users via a PHP website. Channel parsing code, and presentation website, are not part of this project.

Latest release of Dyefechase have been also tested under UNIX environment using a MySql database.

Quick start
-----------

1. Edit PHP script by writing down configuration data where requested (at the very top of the file).

2. Create and populate the input text file containing the list of websites where to look for channels, each website URL on a separate line.

3. Launch your search. If you operate under a UNIX environment, you could launch, as an example, "nohup sh dyefechase.sh &> /dev/null &" 

Contributions & Bug reports
---------------------------

Dyefechase is a GitHub project.

Browse to https://github.com/msoderi/DYEFECHASE to contribute and/or report bugs.
