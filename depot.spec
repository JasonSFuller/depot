# rpmbuild -ba depot.spec --define 'version x.y.z' --define 'release 2'
%define name depot
%{!?version: %define version 0.0.2}
%{!?version: %define release 1}

Name:          %{name}
Version:       %{version}
Release:       %{release}
Summary:       Simple file serve with AD auth
Group:         System/Base
License:       GPLv3
URL:           https://github.com/JasonSFuller/depot
Source0:       %{name}-%{version}.tar.gz
BuildArch:     noarch
#BuildRoot:     %(mktemp -ud %{_tmppath}/%{name}-%{version}-%{release}-XXXXXX)
BuildRoot:     %(echo  %{_tmppath}/%{name}-%{version}-%{release}-XXXXXX)
#BuildRequires: wget
Requires:      httpd openssl mod_ssl php php-ldap php-mcrypt



%description
Simple file serve with AD auth



%prep
[[ "%{buildroot}" != "/" ]] && rm -rf %{buildroot}



%setup -T -c -n %{name}
if [[ ! -f "%{SOURCE0}" ]]; then
  wget -O "%{SOURCE0}" "https://github.com/JasonSFuller/%{name}/archive/%{version}.tar.gz"
fi
echo "$RPM_BUILD_DIR/%{name}"
tar -C "$RPM_BUILD_DIR/%{name}" -xzvf "%{SOURCE0}"



%build



%install
rm -rf   %{buildroot}
mkdir -p %{buildroot}/etc/httpd/conf.d
mkdir -p %{buildroot}/opt/depot/www
mkdir -p %{buildroot}/opt/depot/shared
cp       httpd.conf  %{buildroot}/etc/httpd/conf.d/depot.conf
cp       depot.conf  %{buildroot}/opt/depot/
cp -r    www/*       %{buildroot}/opt/depot/www/



%clean
[[ "%{buildroot}" != "/" ]] && rm -rf %{buildroot}



%files
%defattr(644, root, root, 755)
%doc README.md
%config(noreplace) /etc/httpd/conf.d/depot.conf
%config(noreplace) /opt/depot/depot.conf
/opt/depot/www/*
/opt/depot/shared



%changelog
* Wed Aug 12 2015 Jason Fuller <JasonSFuller@gmail.com> 0.0.1
- adding spec file and rpm build stuff
* Wed Aug 12 2015 Jason Fuller <JasonSFuller@gmail.com> 0.0.1
- initial creation
