# syntax=docker/dockerfile:1.12

FROM postgres:18.4-bookworm

COPY --chown=postgres:postgres docker/production/backup/backup.sh /usr/local/bin/atlas-backup
RUN chmod 0755 /usr/local/bin/atlas-backup

USER postgres
ENTRYPOINT ["atlas-backup"]
CMD ["create"]
