DEPOT
=====

A simple web file serve with AD auth


PREREQUISITES
-------------

    sudo yum -y install git rpmbuild rpmdevtools
    git config --global user.name  'First Lastname'
    git config --global user.email 'first.lastname@example.local'
    rpmdev-setuptree
    mkdir ~/src
    cd ~/src
    git clone git@github.com:JasonSFuller/depot


BUILD
-----

    cd ~/src/depot/
    rpmbuild -ba depot.spec --define 'version x.y.z' --define 'release 1'


TODO
----

 * check for infinite loop during https detection
 * pass in HTTP challenge/response username/password (for curl, wget, etc)
 * RPM install
 * require HTTPS in apache conf
 * upload to dir
 * MD5 sum modal window?


Sources
-------

 * https://www.exchangecore.com/blog/how-use-ldap-active-directory-authentication-php/
 * http://eddmann.com/posts/securing-sessions-in-php/
 * http://getbootstrap.com/examples/signin/
 * http://www.directorylister.com/
 * http://fortawesome.github.io/Font-Awesome/icons/
 * http://stackoverflow.com/questions/2637945/getting-relative-path-from-absolute-path-in-php
 * http://www.media-division.com/php-download-script-with-resume-option/
 * http://www.freeformatter.com/mime-types-list.html
