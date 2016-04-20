FROM tutum/lamp:latest

MAINTAINER Tech_Query "shiy007@qq.com"


RUN  apt-get install php5-sqlite;
RUN  rm -rf /app; \
     git clone https://git.oschina.net/Tech_Query/EasyWiki.git /app;

EXPOSE 80 3306

CMD ["/run.sh"]