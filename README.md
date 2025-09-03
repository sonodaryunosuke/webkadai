# PHP + Nginx + MySQL (Docker Compose)

このリポジトリは **PHP 8.4 (FPM, Alpineベース) + Nginx + MySQL 8.4** の開発環境を Docker Compose で構築するためのサンプルです。  
`public/` ディレクトリをドキュメントルートとして利用し、アップロード画像を `image` ボリュームに永続化します。

---

## 📂 ディレクトリ構成

├── Dockerfile
├── compose.yml
├── nginx/
│ └── conf.d/ # Nginxの仮想ホスト設定
├── php.ini # PHP設定ファイル
├── public/ # Webルート (DocumentRoot)
└── README.md


---

## 🚀 セットアップ手順
sudo yum install -y docker
sudo systemctl start docker
sudo systemctl enable docker

sudo usermod -a -G docker ec2-user

compose　install方法
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
### 1. イメージのビルド & コンテナ起動
```bash
docker compose up -d --build

##sql
docker exec -it mysql mysql -u root example_db
bbs_entries

column id | body           | created_at          | image_filename 



PHPで作成した掲示板です。






