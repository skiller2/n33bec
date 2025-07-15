# Set this to your URL - it must return a 200 OK when called, not a redirect.
#export URL={{kiosk.url}}
export URL="http://localhost/pepafront/#!/display"

# Dont want screensavers or screen blanking
xset s off &
xset -dpms &
xset s noblank &

# Hide the mouse cursor
unclutter -idle 0 -noevents &

# Sit and wait until you can hit the URL you'll be showing in the kiosk
while ! curl -s -o /dev/null -w "%{http_code}" ${URL} | grep -q "200"; do
  sleep 1
done

# get screen resolution
WIDTH=`sudo fbset -s | grep "geometry" | cut -d " " -f6`
HEIGHT=`sudo fbset -s | grep "geometry" | cut -d " " -f7`

# Open chrome in incognito mode + kiosk mode
/usr/bin/chromium-browser --window-size=${WIDTH},${HEIGHT} --window-position=0,0 --incognito --autoplay-policy=no-user-gesture-required  --kiosk ${URL}
