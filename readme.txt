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

Quick start
-----------

1. Edit PHP script by writing down database connection information where requested.

2. Create database tables using provided diefechase.sql script.

3. Create the folder structure as follows:

project root folder (give it a name of your choice)
|
----> diefechase.php [file]
|
----> diefechase.sh [file]
|
----> cwebsites [file]
|
----> commands [folder]
|     |
|     ----> [ create this folder but leave it empty at setup, you will add files to force execution termination in the future if needed ]
|
----> tmp [folder]
|     |
|     ----> channelsd [folder]
|           |
|           ----> fetchedpage [folder]
|                 |
|                 ----> [ create this folder but leave it empty at setup, script will populate it during execution ]
|
----> log [ folder ]
      |
      ----> channelsd [ folder]
            |
            ----> [create this folder but leave it empty at setup, script will populate it during execution ]

4. Populate cwebsites input text file, by writing URLs where the script should search for RSS, ATOM and RDF channels. URLs have to be written one per line. The script will search five level depth starting from each of the provided URLs.

5. Launch search. If you operate under a UNIX environment, you could launch, as an example, "nohup sh diefechase.sh &> /dev/null &" 