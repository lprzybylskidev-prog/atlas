FROM postgres:18-bookworm

COPY docker/production/backup/backup.sh /usr/local/bin/atlas-backup

RUN chmod +x /usr/local/bin/atlas-backup

ENTRYPOINT ["atlas-backup"]
