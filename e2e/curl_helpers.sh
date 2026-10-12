B=http://127.0.0.1:8000
login() { # email pass jar
  rm -f $3
  tok=$(curl -s -c $3 -b $3 $B/login | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -c $3 -b $3 -o /dev/null -w "%{http_code} -> %{redirect_url}\n" -X POST $B/login --data-urlencode "_token=$tok" --data-urlencode "email=$1" --data-urlencode "password=$2"
}
get() { curl -s -b $2 -c $2 -o /tmp/t/out.html -w "%{http_code} %{redirect_url}" $B/$1; echo; }
