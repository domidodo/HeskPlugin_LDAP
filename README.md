<a name="readme-top"></a>

<div align="center">
  <a href="https://github.com/domidodo/HeskPlugin_LDAP/graphs/contributors">
    <img src="https://img.shields.io/github/contributors/domidodo/HeskPlugin_LDAP.svg?style=for-the-badge">
  </a>
  <a href="https://github.com/domidodo/HeskPlugin_LDAP/network/members">
    <img src="https://img.shields.io/github/forks/domidodo/HeskPlugin_LDAP.svg?style=for-the-badge">
  </a>
  <a href="https://github.com/domidodo/HeskPlugin_LDAP/stargazers">
    <img src="https://img.shields.io/github/stars/domidodo/HeskPlugin_LDAP.svg?style=for-the-badge">
  </a>
  <a href="https://github.com/domidodo/HeskPlugin_LDAP/issues">
    <img src="https://img.shields.io/github/issues/domidodo/HeskPlugin_LDAP.svg?style=for-the-badge">
  </a>
  <a href="https://github.com/domidodo/HeskPlugin_LDAP/blob/main/LICENSE">
    <img src="https://img.shields.io/github/license/domidodo/HeskPlugin_LDAP.svg?style=for-the-badge">
  </a>
  
  <!-- PROJECT LOGO -->
  <br />
  <br />
  <a href="https://github.com/domidodo/HeskPlugin_LDAP">
    <img src="HeskPlugin_LDAP_logo.png" alt="Logo" width="159" height="100">
  </a>

  <h3 align="center">Hesk-Plugin: LDAP</h3>

  <p align="center">
    An unofficial plugin for the Hesk.com Help Desk 
    <br />
    <a href="https://www.hesk.com/"><strong>Go to hesk.com »</strong></a>
    <br />
    <br />
    <a href="https://github.com/domidodo/HeskPlugin_LDAP/issues">Report Bug</a>
    ·
    <a href="https://github.com/domidodo/HeskPlugin_LDAP/issues">Request Feature</a>
  </p>
</div>


## Table of Contents
<!-- TABLE OF CONTENTS -->
<ol>
  <li><a href="#table-of-contents">Table of Contents</a></li>
  <li>
    <a href="#about-the-project">About The Project</a>
    <ul>
      <li><a href="#built-with">Built With</a></li>
    </ul>
  </li>
  <li>
    <a href="#getting-started">Getting Started</a>
    <ul>
      <li><a href="#prerequisites">Prerequisites</a></li>
      <li><a href="#installation">Installation</a></li>
    </ul>
  </li>
  <li><a href="#usage">Usage</a></li>
  <li><a href="#contributing">Contributing</a></li>
  <li><a href="#license">License</a></li>
</ol>



<!-- ABOUT THE PROJECT -->
## About The Project

This is a unofficial plugin for the Hesk.com Help Desk.

You can use it to connect the Help Desk with your LDAP-Server to synchronize your agents.
All the synchronized agents get the admin-role by default.

Here's why:
* An LDAP-Connection is a requested functionality in the community
* It's always a good idea to use LDAP :smile:

<p align="right">(<a href="#readme-top">back to top</a>)</p>



### Built With

* [![PHP][Php]][Php-url]


<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- GETTING STARTED -->
## Getting Started

Just a few steps and you can use LDAP on Hesk.


### Prerequisites

You need to enable the LDAP-extension in the php.ini
* Windows (XAMPP)
  ```ini
  extension=ldap
  ```


* Linux <br />
  For Debian, the installation command would be `apt-get install php-ldap`. <br />
  For RHEL based systems, the command would be `yum install php-ldap`.

  ```ini
  extension=php_ldap.so
  ```
  (`/etc/php/[verion]/apache2/php.ini`)<br />
  Don't forget to restart the service after the changes: `service apache2 restart`

<a href="https://www.php.net/manual/de/book.ldap.php">More »</a>


### Installation

1. Download and install hesk (<a href="https://www.hesk.com/download.php">Download</a> · <a href="https://www.hesk.com/demo/docs/step-by-step-guide.html">Step by step install guide</a>)
3. Clone the repo
   ```sh
   git clone https://github.com/domidodo/HeskPlugin_LDAP.git
   ```
2. Copy the plugin-directory into your hest-root-directory
   ```sh
   cp -r HeskPlugin_LDAP/plugin/* /var/www/html/hesk/plugin/
   ```
4. Open `ldap_settings.inc.php` and setup your LDAP-connection
   ```sh
   nano plugin/ldap_settings.inc.php
   ```
5. Setup the Cronjob by crontab
   ```sh
   crontab -e
   ```
   and add this line:
   ```sh
   */5 * * * * curl -s http://127.0.0.1/hesk/plugin/ldap.php?key=[##URL Access Key##] > /dev/null # Every 5 minutes
   ```
   If you have `URL Access Key` set in `Admin panel > Settings > Help desk > Security > URL Access Key` (which you should), you will need to replace `[##URL Access Key##]` with it to access it via an URL addres.
6. reload Cronjob-service
   ```sh
   service cron reload
   ```



<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- USAGE EXAMPLES -->
## Usage

Your agents will by synchronized in the time interval specified in the cron job.

It is not posible to edit the LDAP-User-Password over the Hesk-UI.
If yout try it, it will have no effect. 

It is posible to add local Hesk-User. They can edit the password over the Hesk-UI.

If there is a LDAP-User with the same mail-address like a local Hesk-User, both accounts will be linked and the LDAP-user-password will be the valid one.

For the case that the LDAP server cannot be reached, the passwords are encrypted stored in the database.

<p align="right">(<a href="#readme-top">back to top</a>)</p>


<!-- CONTRIBUTING -->
## Contributing

If you have a suggestion that would make this better, please fork the repo and create a pull request. You can also simply open an issue with the tag "enhancement".
Don't forget to give the project a star! Thanks again!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- LICENSE -->
## License

Distributed under the Apache License 2.0 License. See `LICENSE` for more information.

<p align="right">(<a href="#readme-top">back to top</a>)</p>



<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[Php]: https://img.shields.io/badge/php-35495E?style=for-the-badge&logo=php&logoColor=7a86b8
[Php-url]: https://www.php.net/
