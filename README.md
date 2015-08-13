DEPOT
=====

A simple web file serve with AD auth


BUILD PREREQUISITES
-------------------

    sudo yum -y install git rpmbuild rpmdevtools
    git config --global user.name  'First Lastname'
    git config --global user.email 'first.lastname@example.local'
    rpmdev-setuptree
    mkdir ~/src
    cd ~/src
    git clone https://github.com/JasonSFuller/depot.git


BUILD
-----

    cd ~/src/depot/
    rpmbuild -ba depot.spec --define 'version x.y.z' --define 'release 1'


INSTALLATION
------------

EPEL is required for php-mcrypt.  If CentOS Extras is installed (which it is, by default), you should simply be able to:

    sudo yum -y install epel-release
    
Otherwise, pick the appropriate section for your release:

    # centos6, rhel6, ol6, etc
    wget http://dl.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm
    sudo rpm -Uvh epel-release-6*.rpm
    
    # centos7, rhel7, ol7, etc
    wget http://dl.fedoraproject.org/pub/epel/7/x86_64/e/epel-release-7-5.noarch.rpm
    sudo rpm -Uvh epel-release-7*.rpm
    
Then, once the RPM is build (above), you can simply:

    sudo yum install /path/to/depot-x.y.z.DIST.rpm


TODO
----

 * check for infinite loop during https detection
 * pass in HTTP challenge/response username/password (for curl, wget, etc)
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
