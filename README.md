# POS

Simple POS system.

## Docker build & test

Build the image locally and run a container:

```bash
docker build -t pos-app .
docker run -p 8080:80 --rm pos-app
```

To inspect enabled Apache modules inside the running container:

```bash
docker run --rm pos-app bash -c "ls /etc/apache2/mods-enabled && apachectl -M | grep mpm || true"
```

