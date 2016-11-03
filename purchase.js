function main() {
  return Events({
    from_date: params.from_date,
    to_date: params.to_date,
    event_selectors: [{ event: "Submit Cart" }]
  })
    .filter(function (event) {
      var date = new Date(event.time - (60*60*9));
      var hour = date.getUTCHours();
      return hour == parseInt(params.hour);
    })
    ;
}
