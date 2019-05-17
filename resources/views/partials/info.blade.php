# Info

Welcome to the generated API reference.
@if($showPostmanCollectionButton)
@if($outputPath === '.')
[Get Postman Collection](collection.json)
@else
[Get Postman Collection]({{url($outputPath.'/collection.json')}})
@endif
@endif